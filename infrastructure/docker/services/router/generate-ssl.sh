#!/usr/bin/env bash

# Script used in dev to generate a basic SSL cert

BASE=$(dirname $0)

rm -rf mkdir $BASE/certs/

CERTS_DIR=$BASE/etc/ssl/certs

mkdir -p $CERTS_DIR

openssl req -x509 -sha256 -newkey rsa:4096 \
    -keyout $CERTS_DIR/key.pem \
    -out $CERTS_DIR/cert.pem \
    -days 3650 -nodes -config \
    $BASE/openssl.cnf
