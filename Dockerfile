FROM bitnami/wordpress-nginx:latest

COPY --chown=1001:1001 ./wp-content /app/wp-content
COPY --chown=1001:1001 ./wp-config.php /app/wp-config.php

# 3. Copy configuration overrides
COPY --chown=1001:1001 ./uploads.ini /opt/bitnami/php/etc/conf.d/uploads.ini
COPY --chown=1001:1001 ./php.ini /opt/bitnami/php/etc/conf.d/php.ini
COPY --chown=1001:1001 ./nginx.conf /opt/bitnami/nginx/conf/server_blocks/wordpress-server-block.conf
COPY --chown=1001:1001 ./wp-cli.yml /opt/bitnami/wp-cli/wp-cli.yml
COPY --chown=1001:1001 ./wp-cli.local.yml /opt/bitnami/wp-cli/wp-cli.local.yml

# 3.1. Set up a volume for persistent uploads
VOLUME ["/app/wp-content/uploads"]

# 4. Expose standard ports
EXPOSE 8080 8443

# 5. Let Bitnami's built-in initialization script manage the services
CMD [ "/opt/bitnami/scripts/wordpress/run.sh" ]