FROM alpine:3.22
RUN apk add --no-cache restic rclone postgresql-client bash tzdata
COPY scripts/backup.sh /usr/local/bin/backup
RUN chmod +x /usr/local/bin/backup
ENTRYPOINT ["/usr/local/bin/backup"]
