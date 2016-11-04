<!-- CSS -->
<link href="/plugins/ActiveWireframe/static/css/catalogs/tree.css?v=<?= $this->version; ?>" rel="stylesheet">

<?php if (isset($this->noChilds)) { ?>

    <div class="alert alert-warning alert-tree">
        <strong><?= $this->ts('active_wireframe_warning') ?></strong>&nbsp;
        <?= $this->ts('active_wireframe_no_pages_in_catalog'); ?>
    </div>

<?php } else { ?>
    <style>
        #block-tree .row-preview {
            width: calc((<?= $this->widthPage  . "mm" ?>/<?= $this->reduction ?>) * 2);
            height: calc(<?= $this->heightPage . "mm"?> / <?= $this->reduction?>);
        }
        #block-tree .preview-page {
            width: calc(<?= $this->widthPage . "mm"?> / <?= $this->reduction?>);
            height: calc(<?= $this->heightPage . "mm"?> / <?= $this->reduction?>);
        }
        #block-tree.position-icon-th .row-preview {
            width: calc(((<?= $this->widthPage . "mm"?>/<?= $this->reduction ?>) * 2) / 2.5);
            height: calc((<?= $this->heightPage . "mm"?>/<?= $this->reduction ?>) / 2.5);
        }
        #block-tree.position-icon-th .row-preview .preview-page {
            width: calc((<?= $this->widthPage . "mm"?>/<?= $this->reduction ?>) / 2.5);
            height: calc((<?= $this->heightPage . "mm"?>/<?= $this->reduction ?>) / 2.5);
        }
    </style>

    <div id="block-tree" class="chapter-tree container-lazy">
        <?php

        $startBloc = true;
        $endBlock = $this->pages[0]['indice']%2;
        foreach ($this->pages as $keyDocument => $document) {
            // start bloc
            if ($startBloc) {
                echo "<div class='row-preview'>";
            }

            // Start page odd
            if ($startBloc and $endBlock) {
                $startBloc = false;
            }

            // position
            $classPagePosition = ($document['indice']%2 == 0) ? 'page-left' : 'page-right';

            // notes
            $popoverNotes = "";
            $classNote = "";
            if (!empty($document['notes'])) {
                $classNote = "document-notes";
                $popoverNotes = '<button type="button" class="btn btn-warning btn-page-note icon-book"
                <span class="badge"></span></button>';
            }

            // Workflow
            $strStyleWorkflow = "";
            if (is_array($document['workflow']) and !empty($document['workflow'])) {
                $strStyleWorkflow = "border-color: " . $document['workflow']['color'];
            }

            $divStart = '<div class="preview-page ' . $classNote . ' ' . $classPagePosition . '">';

            $filename = DIRECTORY_SEPARATOR . $document['documentId'] . DIRECTORY_SEPARATOR . $document['documentId'] . '.jpeg';
            $file = "/website/plugins-data" . DIRECTORY_SEPARATOR . \ActiveWireframe\Plugin::PLUGIN_NAME . $filename;
            $absoluteFile  = \ActiveWireframe\Plugin::PLUGIN_PATH_DATA . $filename;

            if (file_exists($absoluteFile)) {
                $img = '<img class="lazy page-image page-border" data-original="' . $file
                    . '?_t=' . time() . '" title="' . $document['key'] . '" style="' . $strStyleWorkflow . '"/>';
            } else {
                $img = '<div class="no-preview page-image page-border"></div>';
            }

            $pageNumber = '<p class="titre-page">' . $document['key'] . '</p>';
            $divEnd = "</div>";
            echo $divStart . $img . $pageNumber . $popoverNotes . $divEnd;

            if ($endBlock) {
                echo '</div>';
            }

            if ($document['indice']%2 == 1 and $this->pages[$keyDocument+1]['indice']%2 == 0) { //even
                // n = impair et n+1 == paire
                $startBloc = $startBloc ? false : true;
                $endBlock = $endBlock ? false : true;

            } elseif ($document['indice']%2 == 0 and $this->pages[$keyDocument+1]['indice']%2 == 1) {
                // n = pair et n+1 == impair
                $startBloc = $startBloc ? false : true;
                $endBlock = $endBlock ? false : true;

            } elseif ($document['indice']%2 == 0 and $this->pages[$keyDocument+1]['indice']%2 == 0) {
                // n = pair et n+1 = pair
                echo '</div>'; // end

            } elseif ($document['indice']%2 == 1 and $this->pages[$keyDocument+1]['indice']%2 == 1) {
                // n = impaire et n+1 = impair
                echo "<div class='row-preview'>"; // new block
            }
        }

        ?>
        <p class="clear"></p>
    </div>
<?php } ?>
