FROM traefik:v3.0

COPY traefik /etc/traefik

ARG PROJECT_NAME
RUN sed -i "s/{{ PROJECT_NAME }}/${PROJECT_NAME}/g" /etc/traefik/traefik.yaml

VOLUME [ "/etc/ssl/certs" ]
