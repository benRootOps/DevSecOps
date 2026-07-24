FROM python:3.10-slim

WORKDIR /app

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1


RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*


COPY backend/requirements.txt .
RUN pip install --upgrade pip && \
    pip install --no-cache-dir -r requirements.txt

COPY backend/ .


RUN groupadd -r appuser && useradd -r -g appuser appuser
RUN mkdir -p /app/staticfiles && chown -R appuser:appuser /app
USER appuser

EXPOSE 8000


CMD ["python", "manage.py", "runserver", "0.0.0.0:8000"]