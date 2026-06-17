import os
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'
import warnings
warnings.filterwarnings("ignore")
import mysql.connector
from deep_translator import GoogleTranslator
import json
import sys
import csv
import re
import urllib.request
import urllib.error
from collections import Counter

# ── Database Configuration ────────────────────────────────────────────────────
db_config = {
    'host':     'localhost',
    'user':     'root',
    'password': '',
    'database': 'helpdesk',
    'port':     3306
}

# ── BERT API Config ───────────────────────────────────────────────────────────
BERT_API_URL = 'http://localhost:5001'

# ── Core Dialect Map (always loaded) ─────────────────────────────────────────
DIALECT_MAP = {
    "sunog": "fire",        "kema": "fire",
    "patay": "killed",      "pate": "kill",
    "bunu":  "kill",        "pusil": "shoot",
    "ayuda": "help",        "socorro": "emergency help",
    "kuyaw": "danger",      "peligro": "danger",
    "baha":  "flood",       "tabang": "help",
    "quema": "fire",        "temblor": "earthquake",
    "fuego": "fire",        "inundacion": "flood",
    "tubig": "water",       "basura": "garbage",
    "dalan": "road",        "kalsada": "street",
    "balay": "house",       "pulis": "police",
    "ospital": "hospital",  "eskwela": "school",
    "merkado": "market",    "dagway": "appearance",
    "grabe": "serious",     "dali": "hurry",
    "tabang": "help",       "nawong": "face",
    "nahulog": "fell",      "nasakitan": "hurt",
    "masakiton": "sick",    "delikado": "dangerous",
    "buak": "broken",       "wala": "none",
    "dugay": "long time",   "kusog": "strong",
    "kusog": "fast",        "init": "hot",
    "ulan": "rain",         "hangin": "wind",
    "linog": "earthquake",  "bagyo": "typhoon",
    "apoy": "fire",         "bombero": "firefighter",
    "bomba": "bomb",        "armas": "weapon",
}

# ── Keyword Emotion Classifier ────────────────────────────────────────────────
EMOTION_KEYWORDS = {
    'Urgency': [
        ('fire', 3),        ('flood', 3),       ('earthquake', 3),
        ('emergency', 3),   ('rescue', 3),       ('burning', 3),
        ('sunog', 3),       ('kema', 3),         ('baha', 3),
        ('quema', 3),       ('temblor', 3),      ('fuego', 3),
        ('socorro', 3),     ('inundacion', 3),   ('linog', 3),
        ('bagyo', 3),       ('bombero', 3),      ('firefighter', 3),
        ('typhoon', 3),     ('storm', 2),        ('apoy', 3),
        ('help', 1),        ('tabang', 1),       ('urgent', 2),
        ('danger', 2),      ('accident', 2),     ('agora', 1),
        ('dali', 1),        ('dayon', 1),        ('pabor', 1),
        ('liba', 2),        ('rescue', 3),       ('evacuate', 3),
    ],
    'Distress': [
        ('destroyed', 3),   ('homeless', 3),     ('nothing left', 3),
        ('devastated', 3),  ('machucado', 2),    ('quemado', 2),
        ('burned down', 3), ('uling', 2),        ('abandoned', 2),
        ('nasunog', 2),     ('patay', 2),        ('victim', 2),
        ('ruin', 2),        ('suffer', 2),       ('scared', 2),
        ('wala na', 2),     ('nawala', 2),       ('miedo', 2),
        ('afraid', 2),      ('hopeless', 3),     ('trauma', 2),
    ],
    'Frustration': [
        ('still not', 3),   ('nobody', 2),       ('nothing done', 3),
        ('ignored', 3),     ('no action', 3),    ('unresolved', 3),
        ('dugay na', 2),    ('way tubag', 2),    ('not fixed', 2),
        ('not working', 2), ('weeks', 1),        ('months', 1),
        ('waiting', 2),     ('repeatedly', 2),   ('always', 1),
        ('never', 2),       ('useless', 3),      ('no response', 3),
    ],
    'Concern': [
        ('broken', 2),      ('problem', 2),      ('issue', 2),
        ('unsafe', 2),      ('delikado', 2),     ('worried', 2),
        ('damage', 1),      ('report', 1),       ('buak', 2),
        ('grabe', 1),       ('peligro', 1),      ('concerned', 2),
        ('noticed', 1),     ('observed', 1),     ('pothole', 2),
        ('garbage', 1),     ('stagnant', 2),     ('leaking', 2),
    ],
    'Gratitude': [
        ('salamat', 3),     ('thank you', 3),    ('thanks', 2),
        ('grateful', 3),    ('appreciate', 3),   ('daghan salamat', 3),
        ('maraming salamat', 3), ('god bless', 2), ('gracias', 2),
        ('blessing', 2),    ('helpful', 2),      ('daghang salamat', 3),
        ('napaka', 2),      ('very kind', 2),    ('ginoo', 1),
    ],
    'Satisfaction': [
        ('resolved', 3),    ('fixed', 3),        ('great job', 3),
        ('well done', 3),   ('excellent', 3),    ('satisfied', 3),
        ('solucionao', 3),  ('na ayos', 3),      ('na limpyo', 2),
        ('better now', 2),  ('improved', 2),     ('nalipay', 2),
        ('nindot na', 2),   ('maayo na', 2),     ('na ayo', 2),
        ('happy now', 2),   ('good job', 2),     ('nice work', 2),
    ],
}

POSITIVE_CONTEXT = [
    'nindot kaayo', 'maayong buntag', 'maayong adlaw', 'salamat',
    'beautiful', 'peaceful', 'happy', 'wonderful', 'great day',
    'kape', 'coffee', 'morning', 'estorya', 'amigo', 'nalipay',
    'maganda', 'masaya', 'mabait', 'good morning', 'good day',
]

def keyword_emotion(text):
    text_lower = text.lower()
    scores = {emotion: 0 for emotion in EMOTION_KEYWORDS}
    for emotion, kw_list in EMOTION_KEYWORDS.items():
        for kw, weight in kw_list:
            if kw in text_lower:
                scores[emotion] += weight
    positive_hits = sum(1 for p in POSITIVE_CONTEXT if p in text_lower)
    if positive_hits >= 2:
        scores['Urgency'] = max(0, scores['Urgency'] - (positive_hits * 2))
    if scores['Urgency'] >= 3:
        return 'Urgency'
    if scores['Distress'] >= 3:
        return 'Distress'
    best = max(scores, key=scores.get)
    return best if scores[best] > 0 else 'Neutral'

# ── BERT API Helpers ──────────────────────────────────────────────────────────
def call_bert_api(endpoint, payload):
    url  = f'{BERT_API_URL}/{endpoint}'
    data = json.dumps(payload).encode('utf-8')
    req  = urllib.request.Request(
        url, data=data,
        headers={'Content-Type': 'application/json'},
        method='POST'
    )
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            return json.loads(resp.read().decode('utf-8'))
    except Exception:
        return None

def bert_api_is_running():
    try:
        with urllib.request.urlopen(f'{BERT_API_URL}/health', timeout=3) as r:
            return r.status == 200
    except Exception:
        return False

# ── Load Dialect Datasets ─────────────────────────────────────────────────────
def load_external_datasets():
    global DIALECT_MAP
    base = os.path.dirname(os.path.abspath(__file__))
    files = {
        'bisaya': ('bisaya_dataset_fixed.csv',  'Bisaya Word',  'English Translation'),
        'tausug': ('tausug_dataset_fixed.csv',   'Tausug',       'English Translation'),
        'zambo':  ('zambo_dictionary_fixed.csv', 'word',         'replacement'),
    }
    for name, (fname, wcol, tcol) in files.items():
        path = os.path.join(base, fname)
        if os.path.exists(path):
            try:
                with open(path, encoding='utf-8') as f:
                    for row in csv.DictReader(f):
                        w = row.get(wcol, '').lower().strip()
                        t = row.get(tcol, '').lower().strip()
                        # Only use short clean translations (max 3 words)
                        if w and t and len(t.split()) <= 3:
                            DIALECT_MAP[w] = t
            except Exception:
                pass

def pre_process_dialects(text):
    """Replace dialect words with English before sending to Google Translate."""
    if not isinstance(text, str):
        return ''
    processed = text.lower().strip()
    # Replace longest matches first to avoid partial replacements
    for word in sorted(DIALECT_MAP.keys(), key=len, reverse=True):
        pattern   = r'\b' + re.escape(word) + r'\b'
        processed = re.sub(pattern, DIALECT_MAP[word], processed)
    return processed

def clean_translation(text):
    """
    Clean Google Translate output:
    - Remove parenthetical grammar notes (e.g. 'ang (location marker)')
    - Replace single-word slash alternatives with first option
    - Fix spacing and capitalization
    """
    if not isinstance(text, str):
        return text

    # Remove parenthetical grammar notes e.g. "(location marker)"
    text = re.sub(r'\s*\([^)]{2,50}\)\s*', ' ', text)

    # Replace single-word slash alternatives — keep first word
    # e.g. "house / home" → "house", "beautiful / nice" → "beautiful"
    text = re.sub(r'\b(\w+)\s*/\s*\w+\b', r'\1', text)

    # Remove any remaining isolated slashes
    text = re.sub(r'\s*/\s*', ' ', text)

    # Remove stray double quotes
    text = re.sub(r'"+', '', text)

    # Fix multiple spaces
    text = re.sub(r' {2,}', ' ', text).strip()

    # Remove spaces before punctuation
    text = re.sub(r'\s+([.!?,;:])', r'\1', text)

    # Capitalize first letter of each sentence
    sentences = re.split(r'(?<=[.!?])\s+', text)
    sentences = [s.strip().capitalize() for s in sentences if s.strip()]
    text = ' '.join(sentences)

    return text

def translate_text(text):
    """Translate text to English with dialect pre-processing and cleaning."""
    if not isinstance(text, str) or not text.strip():
        return text

    # Step 1: Replace dialect words
    preprocessed = pre_process_dialects(text)

    # Step 2: Translate via Google
    try:
        translated = GoogleTranslator(source='auto', target='en').translate(preprocessed)
        if not translated:
            translated = preprocessed
    except Exception:
        translated = preprocessed

    # Step 3: Clean the translation output
    cleaned = clean_translation(translated)

    return cleaned

# ── Smart Summary Generator ───────────────────────────────────────────────────
def make_summary(original_text, translated_text, emotion='Neutral', max_bullets=4):
    """
    Generate clean bullet-point summary from translated text.
    - Extracts meaningful sentences
    - Filters out short/empty fragments
    - Capitalizes and punctuates properly
    - Adds context prefix based on emotion
    """
    # Use translated text as base — it's cleaner English
    text = translated_text if translated_text else original_text

    # Split into sentences properly
    raw_sentences = re.split(r'[.!?\n]+', text)

    # Clean each sentence
    sentences = []
    for s in raw_sentences:
        s = s.strip()
        # Skip very short fragments, single words, or junk
        if len(s) < 8:
            continue
        # Skip sentences that are just numbers or punctuation
        if re.match(r'^[\d\W]+$', s):
            continue
        # Capitalize and ensure it ends cleanly
        s = s[0].upper() + s[1:] if s else s
        # Remove trailing commas or semicolons
        s = s.rstrip(',:;')
        sentences.append(s)

    # Remove near-duplicate sentences
    seen = set()
    unique = []
    for s in sentences:
        key = re.sub(r'\s+', ' ', s.lower()[:40])
        if key not in seen:
            seen.add(key)
            unique.append(s)

    # Pick the most relevant sentences
    bullets = unique[:max_bullets]

    if not bullets:
        # Fallback: use first 120 chars of original
        fallback = (translated_text or original_text or '')[:120].strip()
        if fallback:
            return f'• {fallback[0].upper()}{fallback[1:]}'
        return '• No description available.'

    # Format as bullet points
    result = '\n'.join([f'• {b}' for b in bullets])
    return result

# ── Fallback Sentiment (TextBlob) ─────────────────────────────────────────────
def fallback_sentiment(text):
    try:
        from textblob import TextBlob
        blob      = TextBlob(text)
        polarity  = blob.sentiment.polarity
        subj      = blob.sentiment.subjectivity
        pol_label = "Negative" if polarity < -0.1 else "Positive" if polarity > 0.1 else "Neutral"
        return polarity, subj, pol_label
    except Exception:
        return 0.0, 0.5, "Neutral"

# ── Main Processing ───────────────────────────────────────────────────────────
def process_regional_reports():
    try:
        load_external_datasets()

        conn   = mysql.connector.connect(**db_config)
        cursor = conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT id, title, description, status,
                   ai_emotion, ai_polarity, ai_summary,
                   ai_severity, ai_polarity_score, ai_subjectivity_score
            FROM reports
            ORDER BY created_at DESC
            LIMIT 50
        """)
        reports = cursor.fetchall()

        if not reports:
            print(json.dumps({"error": "No reports found in database."}))
            return

        using_bert = bert_api_is_running()

        results = {
            "textblob": {
                "polarity_distribution":     Counter(),
                "subjectivity_distribution": Counter(),
                "corpus_polarity":           0.0,
                "corpus_subjectivity":       0.0
            },
            "roberta": {
                "emotion_distribution": Counter(),
                "dominant_emotion":     "Neutral",
                "per_report":           []
            },
            "insights": {
                "top_themes":        [],
                "sentiment_summary": "",
                "recommendations":   [],
                "risk_flag":         {"active": False, "reason": ""}
            }
        }

        to_process = [r for r in reports if r['ai_emotion'] is None]
        total_pol  = 0.0
        total_subj = 0.0
        all_titles = ""

        # Batch BERT call for new reports
        bert_cache = {}
        if to_process and using_bert:
            batch_result = call_bert_api('analyze_batch', {
                "reports": [{"id": r['id'], "text": r['description']} for r in to_process]
            })
            if batch_result and 'results' in batch_result:
                for item in batch_result['results']:
                    if item.get('id') and 'error' not in item:
                        bert_cache[item['id']] = item

        for report in reports:
            rid = report['id']

            # ── Cached result ─────────────────────────────────────────────────
            if report['ai_emotion'] is not None:
                emo_label      = report['ai_emotion']
                pol_label      = report['ai_polarity']
                summary        = report['ai_summary']
                severity_label = report['ai_severity']
                total_pol     += report['ai_polarity_score'] or 0
                total_subj    += report['ai_subjectivity_score'] or 0
                all_titles    += report['title'] + ' '
                if emo_label in ['Urgency', 'Distress'] or severity_label == 'Critical':
                    results["insights"]["risk_flag"]["active"] = True
                    results["insights"]["risk_flag"]["reason"] = "Critical or Urgent safety concerns detected."

            else:
                # ── Translate with improved pipeline ─────────────────────────
                translated       = translate_text(report['description'])
                translated_title = translate_text(report['title'])
                all_titles      += translated_title + ' '

                # ── BERT API result ───────────────────────────────────────────
                if rid in bert_cache:
                    res       = bert_cache[rid]
                    pol_label = res['polarity_label']
                    polarity  = res['polarity_score']
                    is_urgent = res.get('risk_flag', False)
                else:
                    polarity, subj, pol_label = fallback_sentiment(translated)
                    risk_kw = ['fire','flood','earthquake','emergency','help','danger',
                               'sunog','kema','baha','tabang','quema','temblor','socorro']
                    is_urgent = any(k in translated.lower() for k in risk_kw)

                # ── Emotion via keyword classifier ────────────────────────────
                emo_label = keyword_emotion(translated + ' ' + report['description'])

                total_pol  += polarity
                total_subj += 0.5

                # Severity
                if emo_label == 'Urgency':
                    severity_label = 'Critical'
                elif emo_label == 'Distress':
                    severity_label = 'High'
                elif emo_label in ['Frustration', 'Concern'] or pol_label == 'Negative':
                    severity_label = 'Medium'
                else:
                    severity_label = 'Low'

                # Risk flag
                if emo_label in ['Urgency', 'Distress'] or is_urgent:
                    results["insights"]["risk_flag"]["active"] = True
                    results["insights"]["risk_flag"]["reason"] = "Critical or Urgent safety concerns detected."

                # ── Improved summary ──────────────────────────────────────────
                summary = make_summary(
                    original_text=report['description'],
                    translated_text=translated,
                    emotion=emo_label
                )

                cursor.execute(
                    "UPDATE reports SET ai_emotion=%s, ai_polarity=%s, ai_summary=%s, "
                    "ai_severity=%s, ai_polarity_score=%s, ai_subjectivity_score=%s WHERE id=%s",
                    (emo_label, pol_label, summary, severity_label, polarity, 0.5, rid)
                )
                conn.commit()

            # Aggregate
            results["roberta"]["per_report"].append({
                "id":       rid,
                "emotion":  emo_label,
                "polarity": pol_label,
                "summary":  summary,
                "severity": severity_label
            })
            results["textblob"]["polarity_distribution"][pol_label]     += 1
            results["roberta"]["emotion_distribution"][emo_label]       += 1
            results["textblob"]["subjectivity_distribution"][
                "Subjective" if total_subj > 0.5 else "Objective"
            ] += 1

        # Finalize
        count = max(len(reports), 1)
        results["textblob"]["corpus_polarity"]     = total_pol  / count
        results["textblob"]["corpus_subjectivity"] = total_subj / count
        results["roberta"]["dominant_emotion"]     = results["roberta"]["emotion_distribution"].most_common(1)[0][0]

        stop_words = {
            'the','and','for','was','are','has','not','been','our',
            'with','that','this','from','have','ang','ng','mga','sa',
            'ko','ako','nako','kami','sila','kini','kamo','niya',
        }
        themes = [
            w for w, _ in Counter(all_titles.lower().split()).most_common(20)
            if len(w) > 4 and w not in stop_words
        ][:3]

        results["insights"]["top_themes"] = themes
        results["insights"]["sentiment_summary"] = (
            f"The community is currently expressing mostly "
            f"{results['roberta']['dominant_emotion'].lower()} tones. "
            f"{'Risk alerts are active — urgent safety reports detected.' if results['insights']['risk_flag']['active'] else 'No critical safety alerts detected.'}"
        )
        results["insights"]["recommendations"] = [
            "Prioritize reports flagged with high urgency or critical severity.",
            "Review themes related to common infrastructure complaints.",
            "Allocate resources based on regional cluster density."
        ]

        results["textblob"]["polarity_distribution"]     = dict(results["textblob"]["polarity_distribution"])
        results["textblob"]["subjectivity_distribution"] = dict(results["textblob"]["subjectivity_distribution"])
        results["roberta"]["emotion_distribution"]       = dict(results["roberta"]["emotion_distribution"])

        print(json.dumps(results))

        cursor.close()
        conn.close()

    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    process_regional_reports()
