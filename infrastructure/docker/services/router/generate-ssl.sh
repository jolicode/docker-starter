#!/usr/bin/env bash

BASE=$(dirname $0)/

mkdir $BASE/certs

openssl req -x509 -sha256 -newkey rsa:4096 \
    -keyout $BASE/certs/key.pem \
    -out $BASE/certs/cert.pem \
    -days 3650 -nodes -config \
    $BASE/openssl.cnf
