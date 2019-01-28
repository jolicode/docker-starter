FROM alpine:3.8

RUN apk --no-cache add mariadb

RUN mkdir /var/run/mysqld && chown mysql:mysql /var/run/mysqld

VOLUME ["/var/lib/mysql"]

WORKDIR /usr/local/mysql

EXPOSE 3306

COPY config/my.cnf /etc/mysql/my.cnf
COPY entrypoint.sh /entrypoint.sh

ENV PATH $PATH:/usr/local/mysql/bin:/usr/local/mysql/scripts

ENTRYPOINT ["/entrypoint.sh"]
CMD ["mysqld", "--datadir=/var/lib/mysql", "--user=mysql"]
