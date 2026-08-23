FROM openresty/openresty:latest

# Copier config principale au bon chemin
COPY nginx.conf /usr/local/openresty/nginx/conf/nginx.conf

# Copier les configs supplémentaires
COPY conf.d/ /etc/nginx/conf.d/

RUN mkdir -p /var/log/nginx

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]