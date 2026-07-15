FROM python:3.10-slim

WORKDIR /opt/siem

#copie depuis le dossier siem
COPY requirements.txt .


RUN pip install --no-cache-dir -r requirements.txt

COPY . .

RUN useradd -m -u 1001 appuser && chown -R appuser:appuser /opt/siem

USER 998:998

EXPOSE 8000

CMD ["python","bin/siem.py","--config","etc/config.yaml"]
