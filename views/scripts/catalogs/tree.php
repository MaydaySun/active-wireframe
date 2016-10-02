<!-- CSS -->
<link href="/plugins/ActiveWireframe/static/css/catalogs/tree.css?v=<?= $this->version; ?>" rel="stylesheet">

<?php if (isset($this->noChilds)) { ?>

    <div class="alert alert-warning alert-tree">
        <strong><?= $this->ts('active_wireframe_warning') ?></strong>&nbsp;
        <?= $this->ts('active_wireframe_no_pages_in_catalog'); ?>
    </div>

<?php } else { ?>

    <!-- CSS -->
    <style>
        #block-tree .row-preview {
            width: calc((<?= $this->widthPage  . "mm" ?>/<?= $this->reduction ?>) * 2);
            height: calc(<?= $this->heightPage . "mm"?> / <?= $this->reduction?>);
        }

        #block-tree .preview-page {
            width: calc(<?= $this->widthPage . "mm"?> / <?= $this->reduction?>);
            height: calc(<?= $this->heightPage . "mm"?> / <?= $this->reduction?>);
        }

        #main-section-tree.position-icon-th #block-tree .row-preview {
            width: calc(((<?= $this->widthPage . "mm"?>/<?= $this->reduction ?>) * 2) / 2.5);
            height: calc((<?= $this->heightPage . "mm"?>/<?= $this->reduction ?>) / 2.5);
        }

        #main-section-tree.position-icon-th #block-tree .row-preview .preview-page {
            width: calc((<?= $this->widthPage . "mm"?>/<?= $this->reduction ?>) / 2.5);
            height: calc((<?= $this->heightPage . "mm"?>/<?= $this->reduction ?>) / 2.5);
        }
    </style>

    <!-- Corps -->
    <div id="block-tree" class="chapter-tree container-lazy">
        <?php

        $start = true;
        $iCount = 1;
        $count = count($this->pages);

        foreach ($this->pages as $document) {

            if ($start or ($document['indice'] % 2 == 0)
                or $document['key'] == $this->ts('active_wireframe_first_cover')
                or $document['key'] == $this->ts('active_wireframe_second_cover')
                or $document['key'] == $this->ts('active_wireframe_fourth_cover')
            ) {
                echo "<div class='row-preview'>";
            }

            $classDocumentLock = $document['lock'] ? "document-lock" : "";
            $classPagePosition = ($document['indice'] % 2 == 0) ? 'page-left' : 'page-right';

            if (($document['key'] == $this->ts('active_wireframe_second_cover'))
                or ($document['key'] == $this->ts('active_wireframe_fourth_cover'))
            ) {
                $classPagePosition = 'page-left';
            }

            if (($document['key'] == $this->ts('active_wireframe_first_cover'))
                or ($document['key'] == $this->ts('active_wireframe_third_cover'))
            ) {
                $classPagePosition = 'page-right';
            }

            $popoverNotes = "";
            $classNote = "";
            if (!empty($document['notes'])) {
                $classNote = "document-notes";
                $txtNote = "";
                foreach ($document['notes']['notes'] as $note) {

                    if ($note instanceof \Pimcore\Model\Element\Note) {
                        $txtNote = $txtNote . '<li>'
                            . $note->getTitle()
                            . ' : <span>'
                            . $note->getDescription()
                            . '</span></li>';
                    }

                }

                // popover
                $popoverNotes = '<button 
                type="button" 
                class="btn btn-warning btn-page-note icon-book" 
                data-container="body" 
                data-toggle="popover" 
                data-placement="bottom" 
                data-content="<ul>' . $txtNote . '</ul>" 
                data-html="true" 
                title="Notes de la page"><span class="badge"></span></button>';
            }

            // Plugin Workflow
            $strStyleWorkflow = "";
            if (is_array($document['workflow']) and !empty($document['workflow'])) {
                $strStyleWorkflow = "border-color: " . $document['workflow']['color'];
            }

            $divStart = '<div class="preview-page ' . $classNote . ' ' . $classPagePosition . '">';

            $file = "/website/plugins-data"
                . DIRECTORY_SEPARATOR . \ActiveWireframe\Plugin::PLUGIN_NAME
                . DIRECTORY_SEPARATOR . $document['documentId']
                . DIRECTORY_SEPARATOR . $document['documentId'] . '.jpeg';

            $absoluteFile  = \ActiveWireframe\Plugin::PLUGIN_PATH_DATA
                . DIRECTORY_SEPARATOR . $document['documentId']
                . DIRECTORY_SEPARATOR . $document['documentId'] . '.jpeg';

            if (file_exists($absoluteFile)) {
                $img = '<img class="lazy page-image page-border ' . $classDocumentLock . '" data-original="' . $file
                    . '?_t=' . time() . '" title="' . $document['key'] . '" style="' . $strStyleWorkflow . '"/>';
            } else {
                $img = '<div class="no-preview page-image page-border ' . $classDocumentLock . '"></div>';
            }

            $pageNumber = '<p class="titre-page">' . $document['key'] . '</p>';
            $divEnd = "</div>";
            echo $divStart . $img . $pageNumber . $popoverNotes . $divEnd;

            if (($document['indice'] % 2 == 1)
                or $document['key'] == $this->ts('active_wireframe_first_cover')
                or $document['key'] == $this->ts('active_wireframe_third_cover')
                or $document['key'] == $this->ts('active_wireframe_fourth_cover')
                or ($iCount == $count)
            ) {
                echo '</div>';
            }

            $start = false;
            $iCount++;
        }

        ?>
        <p class="clear"></p>
    </div>

<?php } ?>
