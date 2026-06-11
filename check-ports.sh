#!/bin/bash
# Проверка занятых портов для docker-compose
OUTPUT=/Users/tigranadaman/Projects/TestMicroserviceSystemBroker/.ports-result.txt

echo "=== Занятые порты на $(hostname) ===" > "$OUTPUT"
echo "Дата: $(date)" >> "$OUTPUT"
echo "" >> "$OUTPUT"

for port in 8080 5432 6379 5672 15672; do
  result=$(lsof -iTCP:$port -sTCP:LISTEN -P -n 2>/dev/null | awk 'NR>1{print $1}')
  if [ -n "$result" ]; then
    echo "BUSY  :$port  ($result)" >> "$OUTPUT"
  else
    echo "FREE  :$port" >> "$OUTPUT"
  fi
done

echo "" >> "$OUTPUT"
echo "=== Все слушающие порты ===" >> "$OUTPUT"
lsof -iTCP -sTCP:LISTEN -P -n 2>/dev/null | awk 'NR>1{print $9}' | \
  grep -oE ':[0-9]+$' | sort -t: -k2 -n | uniq >> "$OUTPUT"

cat "$OUTPUT"
