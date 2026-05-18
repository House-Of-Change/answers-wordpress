#!/bin/sh
# Smoke tests for Answers by info.link plugin
# Usage: sh seed/test.sh

BASE_URL="${1:-http://localhost:8080}"
PASS=0
FAIL=0

check() {
    desc="$1"
    url="$2"
    pattern="$3"

    body=$(curl -s "$url")
    if echo "$body" | grep -q "$pattern"; then
        echo "PASS: $desc"
        PASS=$((PASS + 1))
    else
        echo "FAIL: $desc"
        echo "  URL: $url"
        echo "  Expected pattern: $pattern"
        FAIL=$((FAIL + 1))
    fi
}

echo "Running smoke tests against $BASE_URL..."
echo ""

# Product pages - FAQ HTML
check "Blue Snowboard has FAQ section" \
    "$BASE_URL/product/blue-snowboard/" \
    "answers-section"

check "Blue Snowboard has FAQ question" \
    "$BASE_URL/product/blue-snowboard/" \
    "What skill level is the Blue Snowboard"

check "Red Snowboard has FAQ section" \
    "$BASE_URL/product/red-snowboard/" \
    "answers-section"

check "Green Jacket has FAQ section" \
    "$BASE_URL/product/green-jacket/" \
    "answers-section"

# Product pages - JSON-LD
check "Blue Snowboard has FAQPage JSON-LD" \
    "$BASE_URL/product/blue-snowboard/" \
    "FAQPage"

check "Blue Snowboard JSON-LD has schema.org context" \
    "$BASE_URL/product/blue-snowboard/" \
    "schema.org"

# Landing pages
check "Shipping & Returns has FAQ section" \
    "$BASE_URL/shipping-returns/" \
    "answers-section"

check "Shipping & Returns has return policy question" \
    "$BASE_URL/shipping-returns/" \
    "What is your return policy"

check "Shipping & Returns has FAQPage JSON-LD" \
    "$BASE_URL/shipping-returns/" \
    "FAQPage"

# Shortcode page
check "About Our Store has FAQ section (via shortcode)" \
    "$BASE_URL/about-our-store/" \
    "answers-section"

check "About Our Store has gift card question" \
    "$BASE_URL/about-our-store/" \
    "Do you offer gift cards"

# Schema.org microdata
check "FAQ section has Schema.org microdata" \
    "$BASE_URL/product/blue-snowboard/" \
    'itemtype="https://schema.org/FAQPage"'

check "FAQ items have Question microdata" \
    "$BASE_URL/product/blue-snowboard/" \
    'itemtype="https://schema.org/Question"'

# CSS loaded
check "FAQ CSS is enqueued" \
    "$BASE_URL/product/blue-snowboard/" \
    "answers.css"

echo ""
echo "========================================="
echo "  Results: $PASS passed, $FAIL failed"
echo "========================================="

if [ "$FAIL" -gt 0 ]; then
    exit 1
fi
