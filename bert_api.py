"""
bert_api.py — Local Flask API for HelpDesk BERT Model
Place this file in: C:/xamp/htdocs/Helpdesk/
Run with: python bert_api.py
Listens on: http://localhost:5001/analyze
"""

from flask import Flask, request, jsonify
import torch
import torch.nn as nn
import torch.nn.functional as F
from transformers import BertTokenizerFast, BertModel
import json
import os
import re

app = Flask(__name__)

# ── Config ────────────────────────────────────────────────────────────────────
MODEL_DIR  = os.path.join(os.path.dirname(__file__), 'helpdesk_bert_model')
CSV_DIR    = os.path.dirname(__file__)
PORT       = 5001

# ── Dialect Maps ──────────────────────────────────────────────────────────────
DIALECT_MAP = {}

def load_dialect_maps():
    global DIALECT_MAP

    # Bisaya
    bisaya_path = os.path.join(CSV_DIR, 'bisaya_dataset_fixed.csv')
    if os.path.exists(bisaya_path):
        import csv
        with open(bisaya_path, encoding='utf-8') as f:
            for row in csv.DictReader(f):
                w = row.get('Bisaya Word', '').lower().strip()
                t = row.get('English Translation', '').lower().strip()
                if w and t:
                    DIALECT_MAP[w] = t
        print(f'  Bisaya loaded: {len(DIALECT_MAP)} words')

    # Tausug
    tausug_path = os.path.join(CSV_DIR, 'tausug_dataset_fixed.csv')
    if os.path.exists(tausug_path):
        import csv
        before = len(DIALECT_MAP)
        with open(tausug_path, encoding='utf-8') as f:
            for row in csv.DictReader(f):
                w = row.get('Tausug', '').lower().strip()
                t = row.get('English Translation', '').lower().strip()
                if w and t:
                    DIALECT_MAP[w] = t
        print(f'  Tausug loaded: {len(DIALECT_MAP) - before} words')

    # Zambo/Chavacano
    zambo_path = os.path.join(CSV_DIR, 'zambo_dictionary_fixed.csv')
    if os.path.exists(zambo_path):
        import csv
        before = len(DIALECT_MAP)
        with open(zambo_path, encoding='utf-8') as f:
            for row in csv.DictReader(f):
                w = row.get('word', '').lower().strip()
                t = row.get('replacement', '').lower().strip()
                if w and t:
                    DIALECT_MAP[w] = t
        print(f'  Zambo loaded: {len(DIALECT_MAP) - before} words')

    print(f'  Total dialect entries: {len(DIALECT_MAP)}')


def preprocess_dialect(text):
    if not isinstance(text, str):
        return ''
    text = text.lower().strip()
    words = re.findall(r"[\w']+|[.,!?;]", text)
    result = []
    for w in words:
        translation = DIALECT_MAP.get(w, w)
        # Only use translation if it's a single clean word
        # Skip long definitions like "to put or stop in a proper place"
        if translation and len(translation.split()) <= 3:
            result.append(translation)
        else:
            result.append(w)
    return ' '.join(result)


# ── BERT Model ────────────────────────────────────────────────────────────────
class HelpDeskBERT(nn.Module):
    def __init__(self, model_dir, num_sentiment=3, num_emotion=7, dropout=0.3):
        super().__init__()
        self.bert = BertModel.from_pretrained(
    'bert-base-multilingual-cased',
    cache_dir=model_dir
)
        h            = self.bert.config.hidden_size
        self.dropout = nn.Dropout(dropout)
        self.sentiment_head = nn.Sequential(
            nn.Linear(h, 256), nn.ReLU(), nn.Dropout(dropout), nn.Linear(256, num_sentiment)
        )
        self.emotion_head = nn.Sequential(
            nn.Linear(h, 256), nn.ReLU(), nn.Dropout(dropout), nn.Linear(256, num_emotion)
        )

    def forward(self, input_ids, attention_mask):
        p = self.dropout(self.bert(input_ids=input_ids, attention_mask=attention_mask).pooler_output)
        return self.sentiment_head(p), self.emotion_head(p)


# ── Load Model ────────────────────────────────────────────────────────────────
device     = torch.device('cuda' if torch.cuda.is_available() else 'cpu')
tokenizer  = None
bert_model = None
label_cfg  = None

def load_model():
    global tokenizer, bert_model, label_cfg

    if not os.path.exists(MODEL_DIR):
        print(f'ERROR: Model folder not found at {MODEL_DIR}')
        print('Please extract helpdesk_bert_model.zip into your Helpdesk folder.')
        return False

    print(f'Loading BERT model from {MODEL_DIR}...')
    with open(os.path.join(MODEL_DIR, 'label_config.json')) as f:
        label_cfg = json.load(f)

    tokenizer  = BertTokenizerFast.from_pretrained(MODEL_DIR)
    bert_model = HelpDeskBERT(
        model_dir=MODEL_DIR,
        num_sentiment=len(label_cfg['sentiment_labels']),
        num_emotion=len(label_cfg['emotion_labels'])
    ).to(device)
    bert_model.load_state_dict(
        torch.load(os.path.join(MODEL_DIR, 'model_weights.pt'), map_location=device)
    )
    bert_model.eval()
    print(f'Model loaded on: {device}')
    return True


# ── Translate ─────────────────────────────────────────────────────────────────
def safe_translate(text):
    try:
        from deep_translator import GoogleTranslator
        result = GoogleTranslator(source='auto', target='en').translate(text)
        return result if result else text
    except Exception:
        return text


# ── Analyze ───────────────────────────────────────────────────────────────────
def analyze_text(text):
    preprocessed = preprocess_dialect(text)
    translated   = safe_translate(preprocessed)

    enc = tokenizer(
        translated,
        max_length=128,
        padding='max_length',
        truncation=True,
        return_tensors='pt'
    )

    with torch.no_grad():
        so, eo = bert_model(
            enc['input_ids'].to(device),
            enc['attention_mask'].to(device)
        )

    sp = F.softmax(so, dim=1).cpu().numpy()[0]
    ep = F.softmax(eo, dim=1).cpu().numpy()[0]

    sentiment = label_cfg['sentiment_labels'][sp.argmax()]
    emotion   = label_cfg['emotion_labels'][ep.argmax()]

    # Polarity score: Positive - Negative (range -1 to 1)
    pos_idx = label_cfg['sentiment_labels'].index('Positive')
    neg_idx = label_cfg['sentiment_labels'].index('Negative')
    polarity_score = float(sp[pos_idx] - sp[neg_idx])

    # Risk flag
    risk_keywords = ['flood', 'fire', 'danger', 'help', 'emergency',
                     'baha', 'sunog', 'tabang', 'socorro', 'urgent',
                     'patay', 'pusil', 'bunu', 'kema']
    is_urgent = (
        emotion in ['Urgency', 'Distress'] or
        any(k in translated.lower() or k in text.lower() for k in risk_keywords)
    )

    # Severity
    if emotion == 'Urgency' or is_urgent:
        severity = 'Critical'
    elif emotion == 'Distress':
        severity = 'High'
    elif emotion in ['Frustration', 'Concern'] or sentiment == 'Negative':
        severity = 'Medium'
    else:
        severity = 'Low'

    return {
        'sentiment':      sentiment,
        'emotion':        emotion,
        'polarity_score': polarity_score,
        'polarity_label': sentiment,
        'severity':       severity,
        'risk_flag':      is_urgent,
        'translated':     translated,
        'sentiment_conf': float(sp.max()),
        'emotion_conf':   float(ep.max()),
    }


# ── Routes ────────────────────────────────────────────────────────────────────
@app.route('/health', methods=['GET'])
def health():
    return jsonify({
        'status':   'ok',
        'model':    'bert-base-multilingual-cased',
        'device':   str(device),
        'dialect_entries': len(DIALECT_MAP)
    })


@app.route('/analyze', methods=['POST'])
def analyze():
    if bert_model is None:
        return jsonify({'error': 'Model not loaded'}), 503

    data = request.get_json()
    if not data or 'text' not in data:
        return jsonify({'error': 'Missing "text" field in request body'}), 400

    text = data['text'].strip()
    if not text:
        return jsonify({'error': 'Empty text'}), 400

    try:
        result = analyze_text(text)
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/analyze_batch', methods=['POST'])
def analyze_batch():
    """Analyze multiple reports at once — used by bridge_analysis.py"""
    if bert_model is None:
        return jsonify({'error': 'Model not loaded'}), 503

    data = request.get_json()
    if not data or 'reports' not in data:
        return jsonify({'error': 'Missing "reports" field'}), 400

    results = []
    for report in data['reports']:
        try:
            r = analyze_text(report.get('text', ''))
            r['id'] = report.get('id')
            results.append(r)
        except Exception as e:
            results.append({'id': report.get('id'), 'error': str(e)})

    return jsonify({'results': results})


# ── Main ──────────────────────────────────────────────────────────────────────
if __name__ == '__main__':
    print('=' * 50)
    print(' HelpDesk BERT API')
    print('=' * 50)
    print('Loading dialect maps...')
    load_dialect_maps()
    if load_model():
        print(f'\nAPI running at http://localhost:{PORT}')
        print(f'Health check : http://localhost:{PORT}/health')
        print(f'Analyze      : POST http://localhost:{PORT}/analyze')
        print('\nPress CTRL+C to stop.\n')
        app.run(host='0.0.0.0', port=PORT, debug=False)
    else:
        print('\nFailed to start — model not found.')
