FROM webiny/php7-dev:1.0.0
RUN addgroup --gid 1000 irvin
RUN adduser --uid 1000 --gid 1000 irvin