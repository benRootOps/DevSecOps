# import requests

# HEADERS_SECURITE = [
#     "Strict-Transport-Security",   # Force HTTPS
#     "X-Content-Type-Options",      # Bloque MIME sniffing
#     "X-Frame-Options",             # Bloque clickjacking
#     "Content-Security-Policy",     # Contrôle les ressources chargées
#     "X-XSS-Protection",            # Protection XSS (ancien)
# ]

# response = requests.get("https://example.com")

# print("=== Analyse des headers ===")
# for header in HEADERS_SECURITE:
#     valeur = response.headers.get(header)
#     if valeur:
#         print(f"[✅] {header}: {valeur}")
#     else:
#         print(f"[❌] {header}: ABSENT")
#


import subprocess


p=subprocess.run(['ls -al'],shell=True,capture_output=True,text=True)

print(p.stdout)