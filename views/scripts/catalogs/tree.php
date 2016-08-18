<!-- CSS -->
<link href="/plugins/ActiveWireframe/static/css/catalogs/tree.css?v=<?= $this->version; ?>" rel="stylesheet">

<!-- Aucune pages dans le catalogue -->
<?php if (isset($this->noChilds)) { ?>

    <div class="container bs-example">
        <div class="alert alert-warning">
            <strong><?= $this->ts('active_wireframe_warning') ?></strong>&nbsp;
            <?= $this->ts('active_wireframe_no_pages_in_catalog'); ?>
        </div>
    </div>

    <!-- Appercut des pages du catalogue -->
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
    <section id="main-section-tree">

        <div id="block-tree" class="chapter-tree">

            <?php

            $totalFiles = count($this->pages);
            $start = true;

            foreach ($this->pages as $document) {

                // Si i == 0, début du chemin de fer
                if ($start
                    || ($document['indice'] % 2 == 0)
                    || $document['key'] == $this->ts('active_wireframe_first_cover')
                    || $document['key'] == $this->ts('active_wireframe_second_cover')
                    || $document['key'] == $this->ts('active_wireframe_fourth_cover')
                ) {
                    echo "<div class='row-preview'>";
                }

                // Détermine si l'élément est vérouillé
                $classDocumentLock = $document['lock'] ? "document-lock" : "";

                // Détermine la position de la page
                $classPagePosition = ($document['indice'] % 2 == 0) ? 'page-left' : 'page-right';

                if (($document['key'] == $this->ts('active_wireframe_second_cover'))
                    || ($document['key'] == $this->ts('active_wireframe_fourth_cover'))
                ) {
                    $classPagePosition = 'page-left';
                }

                if (($document['key'] == $this->ts('active_wireframe_first_cover'))
                    || ($document['key'] == $this->ts('active_wireframe_third_cover'))
                ) {
                    $classPagePosition = 'page-right';
                }

                // Détermine si la page possède des notes
                $popoverNotes = "";
                $classNote = "";
                if (!empty($document['notes'])) {

                    // Classe
                    $classNote = "document-notes";

                    // Crée la chaine HTML des notes
                    $txtNote = "";

                    foreach ($document['notes']['notes'] as $note) {

                        $txtNote = $txtNote . '<li>'
                            . $note->getTitle()
                            . ' : <span>'
                            . $note->getDescription()
                            . '</span></li>';
                    }

                    $txtNote = '<ul>' . $txtNote . '</ul>';

                    // Création du popover
                    $popoverNotes = '<button 
                    type="button" 
                    class="btn btn-warning btn-page-note icon-book" 
                    data-container="body" 
                    data-toggle="popover" 
                    data-placement="bottom" 
                    data-content="' . $txtNote . '" 
                    data-html="true" 
                    title="Notes de la page">
                        <span class="badge"></span>
                    </button>';

                }

                // Plugin Workflow
                $strStyleWorkflow = "";
                if (is_array($document['workflow']) && !empty($document['workflow'])) {
                    $strStyleWorkflow = "border-color: " . $document['workflow']['color'];
                }

                // Création du chemin de fer
                $divStart = '<div class="preview-page ' . $classNote . ' ' . $classPagePosition . '">';
                $file = "activetmp" . DIRECTORY_SEPARATOR
                    . \ActiveWireframe\Plugin::PLUGIN_NAME . DIRECTORY_SEPARATOR
                    . $document['documentId'] . DIRECTORY_SEPARATOR
                    . $document['documentId'] . '.png';

                if (file_exists(PIMCORE_DOCUMENT_ROOT . DIRECTORY_SEPARATOR . $file)) {

                    $img = '<img src="' . DIRECTORY_SEPARATOR . $file . '?_t=' . time() . '" 
                    class="page-image page-border ' . $classDocumentLock . '" 
                    title="' . $document['key'] . '" 
                    style="' . $strStyleWorkflow . '
                    "/>';

                } else {

                    $img = '<div class="no-preview page-image page-border ' . $classDocumentLock . '"></div>';

                }

                $pageNumber = '<p class="titre-page">' . $document['key'] . '</p>';
                $divEnd = "</div>";
                echo $divStart . $img . $pageNumber . $popoverNotes . $divEnd;

                // Si le document à un numero impaire
                if (($document['indice'] % 2 == 1)
                    || $document['key'] == $this->ts('active_wireframe_first_cover')
                    || $document['key'] == $this->ts('active_wireframe_third_cover')
                    || $document['key'] == $this->ts('active_wireframe_fourth_cover')
                ) {
                    echo '</div>';
                }

                $start = false;

            }

            ?>

        </div>

    </section>

<?php } ?>
