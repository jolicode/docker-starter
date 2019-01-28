#!/bin/bash

openssl req -x509 -sha256 -newkey rsa:4096 -keyout ssl/myapp.joli-key.pem -out ssl/myapp.joli-cert.pem -days 3650 -nodes -config openssl.cnf
