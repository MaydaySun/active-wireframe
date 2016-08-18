<!-- Fichier CSS -->
<?php if ($this->editmode) { ?>
    <link href="/plugins/ActiveWireframe/static/css/pages/pages.css?v=<?= $this->version; ?>" rel="stylesheet">
<?php } ?>

<?php
// Configurations
$colGrid = $this->gridCol;
$rowGrid = $this->gridRow;
$colGridTmp = 0;
$rowGridTmp = 0;
$pageWidth = number_format($this->pageWidth, 2);
$pageHeight = number_format($this->pageHeight, 2);
$wProduct = ($pageWidth - number_format($this->paddingLeft, 2) - number_format($this->paddingRight, 2)) / $colGrid;
$hProduct = ($pageHeight - number_format($this->paddingTop, 2) - number_format($this->paddingBottom, 2)) / $rowGrid;
?>

<!-- Style CSS généré -->
<style>
    /* Print */

    /* Taille de la page cropmarks */
    #activeWireframe #page-global {
        width: <?= $this->pageWidthLandmark; ?> !important;
        height: <?= $this->pageHeightLandmark; ?> !important;
        top: -<?= $this->pageTop; ?> !important;
        left: -<?= $this->pageLeft; ?> !important;
    <?php if ($this->template != '') { ?> background-image: url('<?= $this->template; ?>');
        background-size: <?= $this->pageWidthLandmark; ?> <?= $this->pageHeightLandmark; ?>;
    <?php } ?>
    }

    /* Taille et position de la page conteneur */
    #activeWireframe #page,
    #activeWireframe #page-w2p {
        width: <?= $this->pageWidth; ?> !important;
        height: <?= $this->pageHeight; ?> !important;
        overflow: hidden;
    }

    #activeWireframe #page {
        top: <?= $this->pageTop; ?> !important;
        left: <?= $this->pageLeft; ?> !important;
    }

    /* box-w2p-full */
    #activeWireframe .box-w2p-full {
        left: -<?= $this->pageLeft; ?> !important;
        top: -<?= $this->pageTop; ?> !important;
    }

    /* Edition */
    <?php if ($this->editmode) { ?>

    /* Taille et position de la page conteneur */
    #activeWireframe #page-global {
        top: 0 !important;
        left: 0 !important;
    }

    #activeWireframe #page {
        overflow: visible;
    }

    #activeWireframe #page-w2p {
        width: 100% !important;
        height: 100% !important;
        overflow: visible;
    }

    #activeWireframe .pimcore_area_dropzone {
        width: <?= $this->pageWidthLandmark; ?> !important;
        height: <?= $this->pageHeightLandmark; ?> !important;
    }

    #activeWireframe #slider-range-v {
        height: <?= $this->pageHeightLandmark; ?> !important;
    }

    #activeWireframe #slider-range-h {
        width: <?= $this->pageWidthLandmark; ?> !important;
    }

    #slider-range-v .slider-handle-v {
        width: calc(<?= $this->pageWidthLandmark . ' + 10px'; ?>) !important;
    }

    #slider-range-h .slider-handle-h {
        height: calc(<?= $this->pageHeightLandmark . ' + 10px'; ?>) !important;
    }

    #box-cropmarks-1,
    #box-cropmarks-3 {
        width: <?= $this->pageWidthLandmark; ?>
    }

    #box-cropmarks-3 {
        top: <?= $this->pageHeightLandmark; ?>;
    }

    #box-cropmarks-2,
    #box-cropmarks-4 {
        height: <?= $this->pageHeightLandmark; ?>
    }

    #box-cropmarks-2 {
        left: calc(300px + <?= $this->pageWidthLandmark; ?>);
    }

    #box-page-1,
    #box-page-3 {
        width: <?= $pageWidth . 'mm' ?>;
    }

    #box-page-3 {
        top: <?= $pageHeight + 5 . 'mm' ?>;
    }

    #box-page-2,
    #box-page-4 {
        height: calc(<?= $pageHeight . 'mm + 1px' ?>);
    }

    #box-page-2 {
        left: calc(<?= $pageWidth + 5 . 'mm + 300px' ?>);
    }

    <?php } ?>

</style>

<!-- Page vérrouillé -->
<?php if ($this->editmode && $this->pageLock) { ?>
    <div id="pageIsLocked"></div>
<?php } ?>

<input type="hidden" name="paddingLeft" value="<?= $this->paddingLeft; ?>"/>
<input type="hidden" name="paddingRight" value=" <?= $this->paddingRight; ?>"/>
<input type="hidden" name="paddingTop" value="<?= $this->paddingTop; ?>"/>
<input type="hidden" name="paddingBottom" value="<?= $this->paddingBottom; ?>"/>
<input type="hidden" name="pageWidthLandmark" value="<?= $this->pageWidthLandmark; ?>"/>
<input type="hidden" name="pageHeightLandmark" value="<?= $this->pageHeightLandmark; ?>"/>

<!-- Corps -->
<section id="main-section-page">

    <section id="page-w2p">

        <!-- Délimitation Fond perdu -->
        <?php if ($this->editmode) { ?>
            <div id="box-cropmarks-1"></div>
            <div id="box-cropmarks-2"></div>
            <div id="box-cropmarks-3"></div>
            <div id="box-cropmarks-4"></div>
        <?php } ?>

        <!-- délimitation pages -->
        <?php if ($this->editmode) { ?>
            <div id="box-page-1"></div>
            <div id="box-page-2"></div>
            <div id="box-page-3"></div>
            <div id="box-page-4"></div>
        <?php } ?>

        <!-- sliders Position -->
        <?php if ($this->editmode) { ?>
            <div id="slider-range-h"></div>
            <div id="slider-range-v"></div>
        <?php } ?>

        <!-- pages -->
        <section id="page-global">

            <!-- Zone de désélection des éléments -->
            <?php if ($this->editmode) { ?>
                <section id="unselected"></section>
            <?php } ?>

            <!-- page -->
            <section id="page">

                <?php

                // Areas
                $areaBlock = $this->areablock("pages-editable", array(
                        "manual" => true,
                        "toolbar" => (!$this->pageLock) ? true : false,
                        "areablock_toolbar" => array(
                            "title" => "Glisser / Déposer",
                            "buttonWidth" => 210,
                            "buttonMaxCharacters" => 35,
                        ),
                        "areaDir" => $this->areadir
                    )
                )->start();

                // Créations des areas
                while ($areaBlock->loop()) {

                    // Init
                    $currentIndex = $areaBlock->currentIndex['key'];
                    $strStyles = "";

                    // Tailles des areas
                    if ((!array_key_exists($currentIndex, $this->elementsData))
                        || ($this->elementsData[$currentIndex]['e_width'] == null)
                    ) {
                        $strStyles .= "width:" . $wProduct . "mm;";
                    } else {
                        $strStyles .= "width:" . $this->elementsData[$currentIndex]['e_width'] . "mm;";
                    }

                    if ((!array_key_exists($currentIndex, $this->elementsData))
                        || ($this->elementsData[$currentIndex]['e_height'] == null)
                    ) {
                        $strStyles .= "height:" . $hProduct . "mm;";
                    } else {
                        $strStyles .= "height:" . $this->elementsData[$currentIndex]['e_height'] . "mm;";
                    }

                    // Position vertical
                    if (($this->activepaginate && $this->elementsData[$currentIndex]['e_top'] == null)
                        || ($this->activepaginate && !array_key_exists($currentIndex, $this->elementsData))
                    ) {

                        // Position de l'area
                        if ($rowGridTmp == 0) {
                            $strTop = number_format($this->paddingTop, 2);
                        } else {
                            $strTopCalc = ($rowGridTmp * $hProduct) + floatval($this->paddingTop);
                            $strTop = number_format($strTopCalc, 2);
                        }
                        $rowGridTmp++;
                        $strStyles = $strStyles . "top:" . $strTop . "mm;";

                    } else if ($this->elementsData[$currentIndex]['e_top'] != null) {

                        $strStyles .= "top:" . $this->elementsData[$currentIndex]['e_top'] . "mm;";

                    } else {

                        $strStyles .= "bottom: 0mm; top: inherit;";
                    }

                    // position Horizontal
                    if (($this->activepaginate && $this->elementsData[$currentIndex]['e_left'] == null)
                        || ($this->activepaginate && !array_key_exists($currentIndex, $this->elementsData))
                    ) {

                        $valueLeftCalc = ($wProduct * $colGridTmp) + floatval($this->paddingLeft);
                        $valueLeft = number_format($valueLeftCalc, 2);
                        $strStyles = $strStyles . "left: " . $valueLeft . "mm;";

                    } else if ($this->elementsData[$currentIndex]['e_left'] != null) {
                        $strStyles .= "left:" . $this->elementsData[$currentIndex]['e_left'] . "mm;";
                    } else {
                        $strStyles .= "right: 0mm; left: inherit;";
                    }

                    // Z-Index de départ de toute area
                    if (!array_key_exists($currentIndex, $this->elementsData)) {
                        $strStyles .= "z-index: 10;";
                    } else {

                        // Controle que le z-index possède au moins la valeur minimal
                        $zIndexNumber = ($this->elementsData[$currentIndex]['e_index'] > 10)
                            ? $this->elementsData[$currentIndex]['e_index']
                            : 10;
                        $strStyles .= "z-index: " . $zIndexNumber . ";";
                    }

                    // Récupère la valeur du style Transform (rotation, scale ...)
                    if (!array_key_exists($currentIndex, $this->elementsData)) {
                        $strStyles .= "transform: none; -webkit-transform: none; -moz-transform: none; -ms-transform: none;";
                    } else {
                        $strStyles .= "transform:" . $this->elementsData[$currentIndex]['e_transform'] . ";";
                        $strStyles .= "-webkit-transform:" . $this->elementsData[$currentIndex]['e_transform'] . ";";
                        $strStyles .= "-moz-transform:" . $this->elementsData[$currentIndex]['e_transform'] . ";";
                        $strStyles .= "-ms-transform:" . $this->elementsData[$currentIndex]['e_transform'] . ";";
                    }

                    // Structure de l'enveloppe de l'area
                    echo '<div class="box-w2p w2p-draggable box-w2p-resizable " data-key="' . $currentIndex . '"  style="' . $strStyles . '" >';

                    if ($this->editmode) {
                        echo '<div class="poignee poignee-top"></div>';
                    }

                    echo '<div class="box-w2p-rotatable box-w2p-design">';

                    $areaBlock->blockConstruct();
                    $areaBlock->blockStart();
                    $areaBlock->content();
                    $areaBlock->blockEnd();
                    $areaBlock->blockDestruct();

                    echo '</div>';
                    if ($this->editmode) {
                        echo '<div class="poignee poignee-bottom"></div>';
                    }
                    echo '</div>';

                    // Compteur
                    if (($rowGridTmp == $rowGrid) && $colGrid > 1) {
                        $rowGridTmp = 0;
                        $colGridTmp++;
                    }

                }

                // Fin de création
                $areaBlock->end();
                ?>

                <!-- Numero de page -->
                <p class="numpage <?= ($this->numPage % 2) ? "numpageright" : "numpageleft" ?>">
                    <?= $this->numPage; ?>
                </p>

            </section>

        </section>

    </section>

</section>

<!-- JS -->
<?php if ($this->editmode) { ?>

    <script src="/plugins/ActiveWireframe/static/js/pages/pages.js?v=<?= $this->version; ?>"></script>

<?php } ?>