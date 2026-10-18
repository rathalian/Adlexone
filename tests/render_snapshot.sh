#!/usr/bin/env bash
# Renders a fixed set of pages from a throwaway copy of the repo and writes the
# normalised HTML to an output directory. Run it before and after a rendering
# change, then diff the two directories:
#
#   tests/render_snapshot.sh /tmp/before
#   tests/render_snapshot.sh /tmp/after
#   diff -ru /tmp/before /tmp/after
#
# EMPTY_APPS=1 disables every application first, which shows the launchpad home.
# The copy keeps the tracked sqlite file untouched (sign-in rewrites password hashes).
set -euo pipefail

OUT="${1:?usage: render_snapshot.sh <output-dir>}"
SRC="${SRC:-$(cd "$(dirname "$0")/.." && pwd)}"
PORT="${PORT:-$((20000 + RANDOM % 20000))}"
WORK="$(mktemp -d)"
trap 'kill "$(cat "$WORK/pid" 2>/dev/null || echo 0)" 2>/dev/null || true; rm -rf "$WORK"' EXIT

mkdir -p "$OUT"
rm -f "$OUT"/*.html
for d in bootstrap config index.php layouts public site src storage translations vendor composer.json; do
    cp -r "$SRC/$d" "$WORK/"
done

php -r '
$p = new PDO("sqlite:" . $argv[1] . "/storage/database/adlexone.sqlite");
$u = $p->prepare("UPDATE users SET password = ? WHERE user_name = ?");
$u->execute([password_hash("snapshot", PASSWORD_DEFAULT), "Administrator"]);
$u->execute([password_hash("snapshot", PASSWORD_DEFAULT), "angela"]);
if (getenv("EMPTY_APPS") === "1") {
    $p->exec("UPDATE applications SET enabled = 0;");
}
' "$WORK"

php -S "127.0.0.1:$PORT" -t "$WORK" >"$WORK/server.log" 2>&1 &
echo $! >"$WORK/pid"
for _ in $(seq 1 50); do
    curl -s -o /dev/null "http://127.0.0.1:$PORT/" && break
    sleep 0.1
done
BASE="http://127.0.0.1:$PORT"

normalise() {
    sed -E \
        -e 's/(name="_csrf" value=")[0-9a-f]+/\1TOKEN/g' \
        -e 's/(data-csrf=")[0-9a-f]+/\1TOKEN/g' \
        -e 's/\?v=[0-9]+/?v=0/g' \
        -e 's/(on line <b>)[0-9]+/\1N/g' \
        -e "s#127\\.0\\.0\\.1:$PORT#HOST#g" \
        -e "s#$WORK#ROOT#g"
}

slug() { printf '%s' "$1" | sed -E 's/[^A-Za-z0-9]+/_/g; s/^_+|_+$//g'; }

fetch() {
    local jar="$1" label="$2" url="$3" file
    file="$OUT/${label}__$(slug "$url").html"
    {
        curl -s -b "$jar" -c "$jar" -D - -o "$WORK/body" "$BASE/$url" \
            | tr -d '\r' | grep -iE '^(HTTP/|Location:)' | normalise || true
        echo '-----'
        normalise <"$WORK/body"
    } >"$file"
}

login() {
    local jar="$1" user="$2"
    curl -s -c "$jar" -b "$jar" -o /dev/null "$BASE/"
    curl -s -c "$jar" -b "$jar" -o /dev/null \
        -d "access_user_name=$user&access_password=snapshot" "$BASE/?action=local"
}

ANON="$WORK/anon.jar"
for url in '' '?controller=login' '?action=local' '?stay=1' '?theme=inlay-ink' '?controller=home'; do
    fetch "$ANON" anon "$url"
done

URLS=(
    ''
    '?controller=home'
    '?controller=nonexistent'
    '?controller=application&app=nope'
    '?manage=builder'
    '?manage=applications'
    '?manage=fields'
    '?manage=item-types'
    '?manage=rules'
    '?manage=security&option=manage_users'
    '?manage=security&option=manage_groups'
    '?manage=settings&option=adlexone_settings'
    '?manage=settings&option=sign_in_settings'
    '?manage=settings&option=advanced_settings'
    '?controller=full_page_view'
    '?controller=print&option=print_item&item_id=1'
    '?controller=print&option=print_item&item_id=2'
    '?controller=print&option=print_item&item_id=99999'
    '?controller=search_management_manage&option=show_item_search'
    '?controller=item_management_manage&option=show_item_types'
    '?item=1'
    '?item=2'
    '?search=1'
)

mapfile -t APPS < <(php -r '
$p = new PDO("sqlite:" . $argv[1] . "/storage/database/adlexone.sqlite");
foreach ($p->query("SELECT a.slug, n.nav_id FROM applications a JOIN application_nav n ON n.application_id = a.application_id ORDER BY a.application_id, n.nav_id") as $r) {
    echo "?application=" . $r["slug"] . "&nav=" . $r["nav_id"] . "\n";
}
' "$WORK")
URLS+=("${APPS[@]}")
mapfile -t ITEMS < <(php -r '
$p = new PDO("sqlite:" . $argv[1] . "/storage/database/adlexone.sqlite");
foreach ($p->query("SELECT item_id FROM items ORDER BY item_id LIMIT 12") as $r) {
    echo "?application=studio&item=" . $r["item_id"] . "\n";
}
' "$WORK")
URLS+=("${ITEMS[@]}")

for user in Administrator angela; do
    jar="$WORK/$user.jar"
    login "$jar" "$user"
    for url in "${URLS[@]}"; do
        fetch "$jar" "$user" "$url"
    done
done

# Second pass: follow the read-only links that the administrator's pages offer.
mapfile -t LINKS < <(
    cat "$OUT"/Administrator__*.html \
        | grep -oE 'href="\?[^"]+"' | sed -E 's/^href="//; s/"$//; s/&amp;/\&/g' \
        | grep -vE 'logoff|delete|move|export|oauth|action=' \
        | sort -u
)
for url in "${LINKS[@]}"; do
    [ -e "$OUT/crawl__$(slug "$url").html" ] && continue
    fetch "$WORK/Administrator.jar" crawl "$url"
done

echo "wrote $(ls "$OUT" | wc -l) snapshots to $OUT"
PHP_ERRORS='<b>(Warning|Notice|Deprecated|Fatal error|Parse error)</b>:'
if grep -qE "$PHP_ERRORS" "$OUT"/*.html; then
    echo "PHP errors present in snapshots:" >&2
    grep -cE "$PHP_ERRORS" "$OUT"/*.html | grep -v ':0$' >&2
fi
