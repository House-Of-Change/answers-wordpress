#!/bin/sh
set -e

WP_PATH=/var/www/html

echo "Waiting for database to be ready..."
until php -r "
\$conn = @new mysqli(getenv('WORDPRESS_DB_HOST'), getenv('WORDPRESS_DB_USER'), getenv('WORDPRESS_DB_PASSWORD'), getenv('WORDPRESS_DB_NAME'));
if (\$conn->connect_errno) { echo \$conn->connect_error . PHP_EOL; exit(1); }
exit(0);
"; do
    echo "  ...waiting"
    sleep 3
done

# Check if WordPress is already installed
if wp core is-installed --path="$WP_PATH" 2>/dev/null; then
    echo "WordPress is already installed. Skipping setup."
    exit 0
fi

echo "Installing WordPress..."
wp core install \
    --path="$WP_PATH" \
    --url="http://localhost:8080" \
    --title="VA Demo Store" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --skip-email

echo "Installing and activating WooCommerce..."
wp plugin install woocommerce --activate --path="$WP_PATH"

echo "Activating Answers plugin..."
wp plugin activate answers --path="$WP_PATH"

echo "Installing and activating Storefront theme..."
wp theme install storefront --activate --path="$WP_PATH"

echo "Configuring WooCommerce..."
wp option update woocommerce_store_address "123 Demo Street" --path="$WP_PATH"
wp option update woocommerce_store_city "Denver" --path="$WP_PATH"
wp option update woocommerce_default_country "US:CO" --path="$WP_PATH"
wp option update woocommerce_currency "USD" --path="$WP_PATH"
wp option update woocommerce_coming_soon "no" --path="$WP_PATH"
wp option update woocommerce_store_pages_only "no" --path="$WP_PATH"
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json --path="$WP_PATH"

echo "Setting permalink structure..."
wp rewrite structure '/%postname%/' --path="$WP_PATH"

# Write .htaccess directly since WP-CLI can't reliably write it
cat > "$WP_PATH/.htaccess" << 'HTEOF'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTEOF

echo "Creating WooCommerce products..."

# Blue Snowboard
BLUE_ID=$(wp wc product create \
    --name="Blue Snowboard" \
    --slug="blue-snowboard" \
    --type=simple \
    --regular_price=299 \
    --description="The Blue Snowboard is our best-selling all-mountain board, perfect for intermediate to advanced riders. Features a medium flex and directional shape for stability at high speeds." \
    --short_description="All-mountain snowboard for intermediate to advanced riders." \
    --status=publish \
    --user=1 \
    --porcelain \
    --path="$WP_PATH")
echo "Created Blue Snowboard (ID: $BLUE_ID)"
wp post meta update "$BLUE_ID" _answers_feed_id "blue-snowboard-faqs" --path="$WP_PATH"

# Red Snowboard
RED_ID=$(wp wc product create \
    --name="Red Snowboard" \
    --slug="red-snowboard" \
    --type=simple \
    --regular_price=349 \
    --description="The Red Snowboard is built for advanced riders who demand precision. Its stiff flex and aggressive camber deliver unmatched edge hold on groomed runs." \
    --short_description="High-performance snowboard for advanced riders." \
    --status=publish \
    --user=1 \
    --porcelain \
    --path="$WP_PATH")
echo "Created Red Snowboard (ID: $RED_ID)"
wp post meta update "$RED_ID" _answers_feed_id "red-snowboard-faqs" --path="$WP_PATH"

# Green Jacket
GREEN_ID=$(wp wc product create \
    --name="Green Jacket" \
    --slug="green-jacket" \
    --type=simple \
    --regular_price=199 \
    --description="The Green Jacket combines warmth and breathability with a 20K waterproof rating. 100g synthetic insulation keeps you comfortable from 15°F to 35°F." \
    --short_description="Waterproof insulated jacket for cold weather riding." \
    --status=publish \
    --user=1 \
    --porcelain \
    --path="$WP_PATH")
echo "Created Green Jacket (ID: $GREEN_ID)"
wp post meta update "$GREEN_ID" _answers_feed_id "green-jacket-faqs" --path="$WP_PATH"

echo "Creating landing pages..."

# Shipping & Returns page
SHIPPING_ID=$(wp post create \
    --post_type=page \
    --post_title="Shipping & Returns" \
    --post_name="shipping-returns" \
    --post_content="<p>We want you to be completely satisfied with your purchase. Below you'll find all the details about our shipping options and return policy.</p>" \
    --post_status=publish \
    --porcelain \
    --path="$WP_PATH")
echo "Created Shipping & Returns page (ID: $SHIPPING_ID)"
wp post meta update "$SHIPPING_ID" _answers_feed_id "returns-shipping" --path="$WP_PATH"

# About Our Store page (with shortcode)
ABOUT_ID=$(wp post create \
    --post_type=page \
    --post_title="About Our Store" \
    --post_name="about-our-store" \
    --post_content='<p>Welcome to the VA Demo Store! We are passionate about snow sports and committed to providing the best gear at competitive prices.</p>

<p>Have questions? Check out our FAQ below:</p>

[answers_faq set="general-store-faqs" heading="Store FAQ"]' \
    --post_status=publish \
    --porcelain \
    --path="$WP_PATH")
echo "Created About Our Store page (ID: $ABOUT_ID)"

echo ""
echo "========================================="
echo "  Setup complete!"
echo "========================================="
echo ""
echo "  Site:    http://localhost:8080"
echo "  Admin:   http://localhost:8080/wp-admin/"
echo "  Login:   admin / admin"
echo ""
echo "  Products:"
echo "    - http://localhost:8080/product/blue-snowboard/"
echo "    - http://localhost:8080/product/red-snowboard/"
echo "    - http://localhost:8080/product/green-jacket/"
echo ""
echo "  Pages:"
echo "    - http://localhost:8080/shipping-returns/"
echo "    - http://localhost:8080/about-our-store/"
echo ""
