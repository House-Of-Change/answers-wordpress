<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Answers_Meta_Box {

    public static function init(): void {
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
        add_action( 'save_post', [ __CLASS__, 'save_meta_box' ] );
    }

    public static function add_meta_box(): void {
        $post_types = [ 'post', 'page', 'product' ];

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'answers_set_id',
                'Answers',
                [ __CLASS__, 'render_meta_box' ],
                $post_type,
                'side',
                'default'
            );
        }
    }

    public static function render_meta_box( $post ): void {
        $value = get_post_meta( $post->ID, '_answers_set_id', true );
        wp_nonce_field( 'answers_meta_box', 'answers_meta_box_nonce' );
        ?>
        <p>
            <label for="answers_set_id">FAQ Set ID:</label><br>
            <input
                type="text"
                id="answers_set_id"
                name="answers_set_id"
                value="<?php echo esc_attr( $value ); ?>"
                placeholder="e.g. blue-snowboard-faqs"
                style="width: 100%;"
            />
        </p>
        <p class="description">
            Enter the FAQ set ID to display on this page. Available sets:
            blue-snowboard-faqs, red-snowboard-faqs, green-jacket-faqs,
            returns-shipping, general-store-faqs
        </p>
        <?php
    }

    public static function save_meta_box( int $post_id ): void {
        if ( ! isset( $_POST['answers_meta_box_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['answers_meta_box_nonce'], 'answers_meta_box' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['answers_set_id'] ) ) {
            $set_id = sanitize_text_field( $_POST['answers_set_id'] );
            update_post_meta( $post_id, '_answers_set_id', $set_id );
        }
    }
}
