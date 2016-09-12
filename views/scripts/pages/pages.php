<?php if ($this->editmode) { ?>
    <link href="/plugins/ActiveWireframe/static/css/pages/pages.css?v=<?= $this->version; ?>" rel="stylesheet">
<?php } ?>
<style>
    #activeWireframe #page-global {
        width: <?= $this->pageWidthLandmark; ?> !important;
        height: <?= $this->pageHeightLandmark; ?> !important;
        top: -<?= $this->pageTop; ?> !important;
        left: -<?= $this->pageLeft; ?> !important;
    <?php if ($this->template != '') { ?> background-image: url('<?= $this->template; ?>');
        background-size: <?= $this->pageWidthLandmark; ?> <?= $this->pageHeightLandmark; ?>;
    <?php } ?>
    }
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
    #activeWireframe .box-w2p-full {
        left: -<?= $this->pageLeft; ?> !important;
        top: -<?= $this->pageTop; ?> !important;
    }
    /* Editmode */
    <?php if ($this->editmode) { ?>
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
        width: calc(<?= $this->pageWidthLandmark; ?> +10px) !important;
    }
    #slider-range-h .slider-handle-h {
        height: calc(<?= $this->pageHeightLandmark; ?> +10px) !important;
    }

    <?php } ?>

</style>

    <!-- Locked-->
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

        <!-- sliders margin -->
        <?php if ($this->editmode) { ?>
            <div id="slider-range-h"></div>
            <div id="slider-range-v"></div>
        <?php } ?>

        <!-- page -->
        <section id="page-global">

            <!--  Lost funds -->
            <?php if ($this->editmode) { ?>
                <div id="box-cropmarks-1"></div>
                <div id="box-cropmarks-2"></div>
                <div id="box-cropmarks-3"></div>
                <div id="box-cropmarks-4"></div>
            <?php } ?>

            <section id="unselected"></section>

            <section id="page">

                <!-- Pages size -->
                <?php if ($this->editmode) { ?>
                    <div id="box-page-1"></div>
                    <div id="box-page-2"></div>
                    <div id="box-page-3"></div>
                    <div id="box-page-4"></div>
                <?php } ?>

                <?php

                $areaBlock = $this->areablock("pages-editable", array(
                        "manual" => true,
                        "toolbar" => (!$this->pageLock) ? true : false,
                        "areablock_toolbar" => array(
                            "title" => "Elements",
                            "buttonWidth" => 210,
                            "buttonMaxCharacters" => 35,
                        ),
                        "areaDir" => $this->areadir
                    )
                )->start();

                while ($areaBlock->loop()) {

                    $currentIndex = $areaBlock->currentIndex['key'];
                    $strStyles = "";

                    // area size
                    if ((!array_key_exists($currentIndex, $this->elementsData))
                        || ($this->elementsData[$currentIndex]['e_width'] == null)
                    ) {
                        $strStyles .= "width: 70mm;";
                    } else {
                        $strStyles .= "width:" . $this->elementsData[$currentIndex]['e_width'] . "mm;";
                    }

                    if ((!array_key_exists($currentIndex, $this->elementsData))
                        || ($this->elementsData[$currentIndex]['e_height'] == null)
                    ) {
                        $strStyles .= "height: 100mm;";
                    } else {
                        $strStyles .= "height:" . $this->elementsData[$currentIndex]['e_height'] . "mm;";
                    }

                    // vertical position
                    if (($this->activepaginate && $this->elementsData[$currentIndex]['e_top'] == null)
                        || ($this->activepaginate && !array_key_exists($currentIndex, $this->elementsData))
                    ) {

//                        // Position de l'area
//                        if ($rowGridTmp == 0) {
//                            $strTop = number_format($this->paddingTop, 2);
//                        } else {
//                            $strTopCalc = ($rowGridTmp * $hProduct) + floatval($this->paddingTop);
//                            $strTop = number_format($strTopCalc, 2);
//                        }
//                        $rowGridTmp++;
//                        $strStyles = $strStyles . "top:" . $strTop . "mm;";

                    } else if ($this->elementsData[$currentIndex]['e_top'] != null) {
                        $strStyles .= "top:" . $this->elementsData[$currentIndex]['e_top'] . "mm;";
                    } else {
                        $strStyles .= "bottom: 0mm; top: inherit;";
                    }

                    // Horizontal position
                    if (($this->activepaginate && $this->elementsData[$currentIndex]['e_left'] == null)
                        || ($this->activepaginate && !array_key_exists($currentIndex, $this->elementsData))
                    ) {

//                        $valueLeftCalc = ($wProduct * $colGridTmp) + floatval($this->paddingLeft);
//                        $valueLeft = number_format($valueLeftCalc, 2);
//                        $strStyles = $strStyles . "left: " . $valueLeft . "mm;";

                    } else if ($this->elementsData[$currentIndex]['e_left'] != null) {
                        $strStyles .= "left:" . $this->elementsData[$currentIndex]['e_left'] . "mm;";
                    } else {
                        $strStyles .= "right: 0mm; left: inherit;";
                    }

                    // Z-Index
                    if (!array_key_exists($currentIndex, $this->elementsData)) {
                        $strStyles .= "z-index: 10;";
                    } else {
                        $zIndexNumber = ($this->elementsData[$currentIndex]['e_index'] > 10)
                            ? $this->elementsData[$currentIndex]['e_index']
                            : 10;
                        $strStyles .= "z-index: " . $zIndexNumber . ";";
                    }

                    // Transform CSS
                    if (!array_key_exists($currentIndex, $this->elementsData)) {
                        $strStyles .= "transform: none; -webkit-transform: none; -moz-transform: none; -ms-transform: none;";
                    } else {
                        $strStyles .= "transform:" . $this->elementsData[$currentIndex]['e_transform'] . ";";
                        $strStyles .= "-webkit-transform:" . $this->elementsData[$currentIndex]['e_transform'] . ";";
                        $strStyles .= "-moz-transform:" . $this->elementsData[$currentIndex]['e_transform'] . ";";
                        $strStyles .= "-ms-transform:" . $this->elementsData[$currentIndex]['e_transform'] . ";";
                    }

                    // Bow-w2p Div
                    echo '<div class="box-w2p w2p-draggable box-w2p-resizable " data-key="' . $currentIndex
                        . '"  style="' . $strStyles . '" >';

                    echo '<div class="poignee poignee-top"></div>';
                    echo '<div class="box-w2p-rotatable box-w2p-design">';

                    $areaBlock->blockConstruct();
                    $areaBlock->blockStart();
                    $areaBlock->content();
                    $areaBlock->blockEnd();
                    $areaBlock->blockDestruct();

                    echo '</div>';
                    echo '<div class="poignee poignee-bottom"></div>';
                    echo '</div>';

//                    // Compteur
//                    if (($rowGridTmp == $rowGrid) && $colGrid > 1) {
//                        $rowGridTmp = 0;
//                        $colGridTmp++;
//                    }

                }

                $areaBlock->end();
                ?>

                <!-- Page number -->
                <p id="number-page" class="numpage <?= ($this->numPage % 2) ? "numpageright" : "numpageleft" ?>">
                    <?= $this->numPage; ?>
                </p>

            </section>

        </section>

    </section>

</section>
<?php if ($this->editmode) { ?>
    <script src="/plugins/ActiveWireframe/static/js/pages/pages.js?v=<?= $this->version; ?>"></script>
<?php } ?>