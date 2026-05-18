<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Answers_Renderer {

    public static function render_html( array $faqs, array $attrs = [] ): string {
        if ( empty( $faqs ) ) {
            return '';
        }

        $heading      = $attrs['heading'] ?? 'Frequently Asked Questions';
        $show_source  = ( $attrs['show_source'] ?? 'yes' ) === 'yes';
        $heading_tag  = $attrs['heading_tag'] ?? 'h2';
        $allowed_tags = [ 'h2', 'h3', 'h4' ];

        if ( ! in_array( $heading_tag, $allowed_tags, true ) ) {
            $heading_tag = 'h2';
        }

        ob_start();
        ?>
        <section class="answers-section" itemscope itemtype="https://schema.org/FAQPage">
            <?php if ( ! empty( $heading ) ) : ?>
                <<?php echo $heading_tag; ?> class="answers-heading"><?php echo esc_html( $heading ); ?></<?php echo $heading_tag; ?>>
            <?php endif; ?>

            <?php foreach ( $faqs as $faq ) : ?>
                <div class="answers-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                    <details>
                        <summary itemprop="name"><?php echo esc_html( $faq['question'] ); ?></summary>
                        <div class="answers-answer" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                            <div itemprop="text">
                                <?php echo wp_kses_post( $faq['answer'] ); ?>
                            </div>
                            <?php if ( $show_source && ! empty( $faq['source'] ) ) : ?>
                                <cite class="answers-source">Source: <?php echo esc_html( $faq['source'] ); ?></cite>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
            <?php endforeach; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public static function render_jsonld( array $faqs ): string {
        if ( empty( $faqs ) ) {
            return '';
        }

        $entities = [];
        foreach ( $faqs as $faq ) {
            $entities[] = [
                '@type'          => 'Question',
                'name'           => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $faq['answer'] ),
                ],
            ];
        }

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];

        return '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
    }
}
