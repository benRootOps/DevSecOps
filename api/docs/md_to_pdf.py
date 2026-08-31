# -*- coding: utf-8 -*-
"""Régénère DOCUMENTATION_BACKEND.pdf depuis DOCUMENTATION_BACKEND.md.

Usage :  python docs/md_to_pdf.py
Dépendances :  pip install markdown xhtml2pdf
"""
import os
import markdown
from xhtml2pdf import pisa

BASE = os.path.dirname(os.path.abspath(__file__))
MD = os.path.join(BASE, "DOCUMENTATION_BACKEND.md")
PDF = os.path.join(BASE, "DOCUMENTATION_BACKEND.pdf")

# Remplacements pour les caractères hors Latin-1 (polices PDF de base).
REMPL = {
    "→": "->", "⇒": "=>", "∈": " dans ", "∪": " U ",
    "−": "-", "≤": "<=", "≥": ">=", "×": "x",
    "…": "...", "•": "-", "–": "-", "—": "-",
    "⚠️": "(!)", "⚠": "(!)", "✅": "[OK]", "❌": "[X]",
    "\U0001f7e2": "*", "\U0001f7e0": "*", "\U0001f449": "=>", "\U0001f511": "",
    "\U0001f4cb": "", "\U0001f680": "",
}


def nettoyer(texte: str) -> str:
    for k, v in REMPL.items():
        texte = texte.replace(k, v)
    return "".join(c if ord(c) < 256 else "" for c in texte)


with open(MD, encoding="utf-8") as f:
    html_body = nettoyer(markdown.markdown(
        f.read(), extensions=["tables", "fenced_code", "sane_lists"]
    ))

CSS = """
@page { size: a4; margin: 1.8cm; }
body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; line-height: 1.4; }
h1 { font-size: 18pt; color: #0b3d91; border-bottom: 2px solid #0b3d91; padding-bottom: 3px; margin-top: 16px; }
h2 { font-size: 13.5pt; color: #14509a; margin-top: 14px; border-bottom: 1px solid #cccccc; padding-bottom: 2px; }
h3 { font-size: 11pt; color: #333333; margin-top: 10px; }
p { margin: 4px 0; }
code { font-family: Courier; font-size: 8.5pt; background-color: #f2f2f2; }
pre { font-family: Courier; font-size: 7.8pt; background-color: #f5f5f5; border: 1px solid #dddddd; padding: 5px; }
table { border-collapse: collapse; width: 100%; margin: 6px 0; }
th, td { border: 1px solid #bbbbbb; padding: 3px 5px; font-size: 8.3pt; text-align: left; vertical-align: top; }
th { background-color: #e8eef7; }
ul, ol { margin: 3px 0 3px 14px; }
li { margin: 1px 0; }
blockquote { color: #555555; border-left: 3px solid #cccccc; padding-left: 8px; }
"""

html = ('<!DOCTYPE html><html><head><meta charset="utf-8">'
        f"<style>{CSS}</style></head><body>{html_body}</body></html>")

with open(PDF, "wb") as out:
    res = pisa.CreatePDF(html, dest=out, encoding="utf-8")

ok = os.path.exists(PDF) and os.path.getsize(PDF) > 0
print("Erreurs pisa :", res.err, "| PDF :", PDF if ok else "ECHEC",
      "|", os.path.getsize(PDF) if ok else 0, "octets")
