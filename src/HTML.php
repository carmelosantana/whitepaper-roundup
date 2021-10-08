<?php

declare(strict_types=1);

namespace carmelosantana\WhitepaperRoundup;

class HTML
{
    public static function embed($file = null)
    {
        switch (pathinfo($file, PATHINFO_EXTENSION)) {
            case 'pdf':
                switch (carbon_get_theme_option('pdf_embed')) {
                    case 'pdfobject':
?>
                        <script>
                            $(document).ready(function() {
                                PDFObject.embed("<?php echo $file; ?>", "#pdf-embed");
                            });
                        </script>
                        <div id="pdf-embed"></div>
                    <?php
                        return true;
                        break;
                }

            default:
            
                switch (carbon_get_theme_option('document_embed')) {
                    default:
                    ?>
                        <iframe src="<?php echo $file; ?>" class="embed-container">
                            This browser does not support .<?php echo pathinfo($file, PATHINFO_EXTENSION); ?>. Please <a href="<?php echo $file; ?>">download</a> to view it.
                        </iframe>
<?php
                        break;
                }
                break;
        }
    }
}
