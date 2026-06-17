# 🏘️ Community Barangay HelpDesk Management System

A web-based community complaint and report management system with **Multilingual NLP Sentiment Analysis** supporting **Bisaya, Tausug, and Chavacano** dialects.

> Aligned with **UN SDG 11 — Sustainable Cities and Communities**

---

## 📋 Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Installation Guide](#installation-guide)
  - [Step 1 — Install XAMPP](#step-1--install-xampp)
  - [Step 2 — Clone the Repository](#step-2--clone-the-repository)
  - [Step 3 — Set Up the Database](#step-3--set-up-the-database)
  - [Step 4 — Set Up Python Environment](#step-4--set-up-python-environment)
  - [Step 5 — Download the BERT Model](#step-5--download-the-bert-model)
  - [Step 6 — Start the BERT API](#step-6--start-the-bert-api)
  - [Step 7 — Launch the Web App](#step-7--launch-the-web-app)
- [Project Structure](#project-structure)
- [Default Login Credentials](#default-login-credentials)
- [Running the AI Insights](#running-the-ai-insights)
- [Troubleshooting](#troubleshooting)
- [Team](#team)

---

## ✨ Features

- 📝 **Complaint Submission** — residents submit reports with title, category, description, and map location pin
- 👥 **Role-Based Access** — Admin, Resident, and Anonymous (Quick Report) roles
- 🗺️ **Map Integration** — MapTiler SDK for geographic location pinning
- 🤖 **AI Sentiment Analysis** — Fine-tuned Multilingual BERT model for:
  - Sentiment classification (Positive / Neutral / Negative)
  - Emotion detection (Urgency, Distress, Frustration, Concern, Gratitude, Satisfaction, Neutral)
  - Automatic risk flagging for emergencies (fire, flood, earthquake)
- 🌐 **Dialect Support** — Pre-processing dictionaries for Bisaya (4,654 words), Tausug (2,333 words), Chavacano (3,347 words)
- 📊 **Admin Insights Dashboard** — polarity gauge, emotion distribution, top themes, recommendations
- ⚡ **Fallback Mode** — TextBlob fallback when AI API is offline

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| Frontend | PHP 8.x, Bootstrap Icons, JavaScript |
| Backend | PHP 8.x, XAMPP (Apache + MySQL) |
| AI Engine | Python 3.10+, PyTorch, HuggingFace Transformers |
| NLP Model | `bert-base-multilingual-cased` (fine-tuned) |
| API Bridge | Flask 3.x (port 5001) |
| Translation | deep-translator (Google Translate) |
| Map | MapTiler SDK |
| Database | MySQL 8.x / MariaDB 10.x |

---

## ⚙️ Requirements

### System Requirements
- **OS:** Windows 10/11 (recommended) or Ubuntu 22.04
- **RAM:** Minimum 8 GB
- **Disk:** Minimum 3 GB free space (for BERT model ~700 MB + dependencies)
- **Internet:** Required for initial setup and Google Translate

### Software Requirements
| Software | Version | Download |
|---|---|---|
| XAMPP | 8.2+ | https://www.apachefriends.org |
| Python | 3.10 or higher | https://www.python.org/downloads |
| Git | Latest | https://git-scm.com |

---

## 🚀 Installation Guide

### Step 1 — Install XAMPP

1. Download XAMPP from https://www.apachefriends.org
2. Install it to `C:\xampp` (Windows) or `/opt/lampp` (Linux)
3. Open **XAMPP Control Panel**
4. Start **Apache** and **MySQL**

---

### Step 2 — Clone the Repository

Open a terminal and run:

```bash
# Navigate to XAMPP's web root
cd C:\xampp\htdocs        # Windows
# OR
cd /opt/lampp/htdocs      # Linux

# Clone the repository
git clone https://github.com/YOUR_USERNAME/helpdesk.git Helpdesk

# Enter the project folder
cd Helpdesk
```

> ⚠️ Replace `YOUR_USERNAME` with your actual GitHub username.

---

### Step 3 — Set Up the Database

1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **New** on the left sidebar
3. Create a database named: `helpdesk`
4. Click the `helpdesk` database
5. Click **Import** tab
6. Click **Choose File** and select: `helpdesk_dgraded_ver10.sql`
7. Click **Go**

You should see a success message. ✅

---

### Step 4 — Set Up Python Environment

Open a terminal **inside the Helpdesk folder**:

```bash
cd C:\xampp\htdocs\Helpdesk    # Windows
# OR
cd /opt/lampp/htdocs/Helpdesk  # Linux
```

**Create a virtual environment:**

```bash
python -m venv .venv
```

**Activate the virtual environment:**

```bash
# Windows
.venv\Scripts\activate

# Linux / Mac
source .venv/bin/activate
```

You should see `(.venv)` at the start of your terminal line.

**Install all Python dependencies:**

```bash
pip install torch transformers flask deep-translator mysql-connector-python textblob sentencepiece accelerate
```

> ⏳ This may take 3–5 minutes depending on your internet speed.

---

### Step 5 — Download the BERT Model

The BERT model weights (~700 MB) are **not included** in the repository due to file size limits.

**Option A — Download from Google Drive (Recommended)**

Ask your team lead to share the `helpdesk_bert_model.zip` file via Google Drive, then:

1. Download `helpdesk_bert_model.zip`
2. Extract it into your Helpdesk folder so it looks like:
```
Helpdesk/
└── helpdesk_bert_model/
    ├── model_weights.pt
    ├── label_config.json
    ├── tokenizer_config.json
    ├── tokenizer.json
    └── vocab.txt
```

**Option B — Re-train the model (if no zip available)**

1. Open Google Colab: https://colab.research.google.com
2. Upload `helpdesk_bert_colab.ipynb` from the repo
3. Set runtime to **T4 GPU** (`Runtime > Change runtime type`)
4. Run all cells
5. Download the generated `helpdesk_bert_model.zip`
6. Extract into the Helpdesk folder

---

### Step 6 — Start the BERT API

Make sure your virtual environment is activated, then run:

```bash
python bert_api.py
```

You should see:
```
==================================================
 HelpDesk BERT API
==================================================
Loading dialect maps...
  Bisaya loaded: 4654 words
  Tausug loaded: 2333 words
  Zambo loaded: 3347 words
  Total dialect entries: 10334
Loading BERT model from .../helpdesk_bert_model...
Model loaded on: cpu
API running at http://localhost:5001
Press CTRL+C to stop.
```

> 💡 Keep this terminal window **open** while using the system. The AI Insights will not work without it.

**Test the API is running:**

Open your browser and go to:
```
http://localhost:5001/health
```

You should see:
```json
{"status":"ok","model":"bert-base-multilingual-cased","device":"cpu","dialect_entries":10334}
```

---

### Step 7 — Launch the Web App

Open your browser and go to:
```
http://localhost/Helpdesk
```

The HelpDesk landing page should appear. ✅

---

## 📁 Project Structure

```
Helpdesk/
├── 📄 index.php                    ← Landing page with Quick Report
├── 📄 Login.php                    ← Login page
├── 📄 register.php                 ← Resident registration
├── 📄 admin_dashboard.php          ← Admin complaint management
├── 📄 resident_dashboard.php       ← Resident report tracking
├── 📄 resident_submit.php          ← Report submission form
├── 📄 insights.php                 ← AI insights API endpoint
├── 📄 submit_report.php            ← Handles report form POST
├── 📄 update_status.php            ← Updates report status
├── 📄 assign_report.php            ← Assigns reports to staff
├── 📄 add_report_action.php        ← Adds action notes
├── 📄 delete_reports.php           ← Deletes reports
├── 📄 db.php                       ← Database connection config
├── 📄 logout.php                   ← Session logout
├── 📄 style.css                    ← Global stylesheet
│
├── 🐍 bert_api.py                  ← Flask REST API for BERT model
├── 🐍 bridge_analysis.py           ← NLP pipeline & dialect processing
│
├── 📊 bisaya_dataset_fixed.csv     ← Bisaya dialect dictionary (4,654 words)
├── 📊 tausug_dataset_fixed.csv     ← Tausug dialect dictionary (2,333 words)
├── 📊 zambo_dictionary_fixed.csv   ← Chavacano dialect dictionary (3,347 words)
│
├── 🗄️ helpdesk_dgraded_ver10.sql   ← Database schema (import this)
│
├── 📓 helpdesk_bert_colab.ipynb    ← Google Colab BERT training notebook
│
└── 📁 helpdesk_bert_model/         ← BERT model files (download separately)
    ├── model_weights.pt
    ├── label_config.json
    ├── tokenizer.json
    └── vocab.txt
```

---

## 🔑 Default Login Credentials

After importing the database, use these credentials:

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Resident | `resident` | `resident123` |

> ⚠️ Change these passwords after first login in a production environment.

---

## 🤖 Running the AI Insights

1. Make sure `bert_api.py` is running (Step 6)
2. Log in as **Admin**
3. Click the **AI Sentiment Insights** button on the dashboard
4. Wait a few seconds for analysis to complete
5. Results will show:
   - Polarity gauge (overall community sentiment)
   - Risk flag (active if fire/flood/earthquake detected)
   - Emotion distribution chart
   - Per-report classification
   - Top reported themes
   - Admin recommendations

---

## 🔧 Troubleshooting

| Problem | Solution |
|---|---|
| `http://localhost/Helpdesk` shows blank | Make sure Apache is running in XAMPP Control Panel |
| Database connection error | Make sure MySQL is running and you imported the SQL file |
| `ModuleNotFoundError` when running bert_api.py | Run `pip install -r requirements.txt` or install missing module manually |
| BERT model not found | Extract `helpdesk_bert_model.zip` into the Helpdesk folder |
| AI Insights shows no data | Make sure `bert_api.py` is running in a separate terminal |
| Port 5001 already in use | Change `PORT = 5001` to `PORT = 5002` in `bert_api.py` and update `bridge_analysis.py` |
| Google Translate fails | Check your internet connection — the system will fallback to TextBlob |
| Disk space error during model load | Set `HF_HOME` to a drive with more space: `set HF_HOME=D:\hf_cache` |
| `(.venv)` not showing | Re-run the activate command for your OS |

---

## 📦 Python Dependencies Reference

```
torch>=2.0
transformers>=5.0
flask>=3.0
deep-translator>=1.11
mysql-connector-python>=9.0
textblob>=0.17
sentencepiece
accelerate
```

---

## 👥 Team

| Name | Role |
|---|---|
| [Your Name] | Full-Stack Developer / AI Engineer |
| [Groupmate 1] | Frontend Developer |
| [Groupmate 2] | Backend Developer |
| [Groupmate 3] | Database Administrator |
| [Groupmate 4] | Documentation / Testing |

---

## 📄 License

This project is developed as a capstone project for academic purposes.

---

> 💬 For questions or issues, contact the team lead or open a GitHub Issue.
