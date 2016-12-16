<link href="http://admin.activepublishing.fr?controller=active-wireframe&action=default-css" rel="stylesheet">
<style>
    #activeWireframe #page-global {
        background-image: url("<?= $this->template; ?>");
        background-size: <?= $this->pageWidthLandmark; ?> <?= $this->pageHeightLandmark; ?>;
    }
    #activeWireframe .box-w2p-full {
        width: <?= $this->pageWidthLandmark ?> !important;
        height: <?= $this->pageHeightLandmark ?> !important;
    }
</style>
<?php if ($this->editmode) { ?>
    <link href="/plugins/ActiveWireframe/static/css/pages/editmode.css?v=<?= $this->version; ?>" rel="stylesheet">
    <style>
        #activeWireframe #page-global {
            width: <?= $this->pageWidthLandmark; ?> !important;
            height: <?= $this->pageHeightLandmark; ?> !important;
        }
        #slider-range-v .slider-handle-v {
            width: calc(<?= $this->pageWidthLandmark; ?> + 10px) !important;
        }
        #slider-range-h .slider-handle-h {
            height: calc(<?= $this->pageHeightLandmark; ?> + 10px) !important;
        }
    </style>
<?php } else { ?>
    <link href="/plugins/ActiveWireframe/static/css/pages/preview.css?v=<?= $this->version; ?>" rel="stylesheet">
    <style>
        #activeWireframe #page-global {
            width: <?= $this->pageWidth; ?> !important;
            height: <?= $this->pageHeight; ?> !important;
        }
    </style>
<?php } ?>

<?php if ($this->editmode and $this->pageLock) { ?>
    <!-- Locked-->
    <div id="pageIsLocked"></div>
<?php } ?>

<input type="hidden" name="paddingLeft" value="<?= $this->paddingLeft; ?>"/>
<input type="hidden" name="paddingRight" value=" <?= $this->paddingRight; ?>"/>
<input type="hidden" name="paddingTop" value="<?= $this->paddingTop; ?>"/>
<input type="hidden" name="paddingBottom" value="<?= $this->paddingBottom; ?>"/>
<input type="hidden" name="pageWidthLandmark" value="<?= $this->pageWidthLandmark; ?>"/>
<input type="hidden" name="pageHeightLandmark" value="<?= $this->pageHeightLandmark; ?>"/>

<div id="page-w2p">

    <!-- page width landmark-->
    <div id="page-global">

        <?php if ($this->editmode) { ?>
            <!-- sliders margin -->
            <div id="slider-range-h"></div>
            <div id="slider-range-v"></div>

            <!-- border #page-global -->
            <div id="border-page-global"></div>

            <!-- border #page -->
            <div id="border-page"></div>

            <!-- unselected area -->
            <div id="unselected"></div>
        <?php } ?>

        <!-- page area -->
        <div id="page">

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

                // New areablock
                if (!array_key_exists($currentIndex, $this->elementsData)) {
                    $styles = "bottom: 0; top: inherit; left: inherit;";

                } else {

                    $styles = "width:" . $this->elementsData[$currentIndex]['e_width'] . "mm;";
                    $styles .= " height:" . $this->elementsData[$currentIndex]['e_height'] . "mm;";
                    $styles .= " top:" . $this->elementsData[$currentIndex]['e_top'] . "mm;";
                    $styles .= " left:" . $this->elementsData[$currentIndex]['e_left'] . "mm;";

                    $zIndexNumber = ($this->elementsData[$currentIndex]['e_index'] > 10)
                        ? $this->elementsData[$currentIndex]['e_index']
                        : 10;
                    $styles .= " z-index: " . $zIndexNumber . ";";

                    $styles .= " transform:" . $this->elementsData[$currentIndex]['e_transform'] . ";";
                    $styles .= " -webkit-transform:" . $this->elementsData[$currentIndex]['e_transform'] . ";";
                    $styles .= " -moz-transform:" . $this->elementsData[$currentIndex]['e_transform'] . ";";
                    $styles .= " -ms-transform:" . $this->elementsData[$currentIndex]['e_transform'] . ";";

                }

                echo '<div class="box-w2p" data-key="' . $currentIndex . '"  style="' . $styles . '" >';
                echo '<div class="box-w2p-handle handle-top"></div>';
                echo '<div class="box-w2p-container">';

                $areaBlock->blockConstruct();
                $areaBlock->blockStart();
                $areaBlock->content();
                $areaBlock->blockEnd();
                $areaBlock->blockDestruct();

                echo '</div>';
                echo '<div class="box-w2p-handle handle-bottom"></div>';
                echo '</div>';

            }

            $areaBlock->end();
            ?>

            <!-- Page number -->
            <p id="number-page" class="numpage <?= ($this->numPage % 2) ? "numpageright" : "numpageleft" ?>">
                <?= $this->numPage; ?>
            </p>

        </div>

    </div>

</div>

<?php if ($this->editmode) { ?>
    <script src="/plugins/ActiveWireframe/static/js/pages/editmode.js?v=<?= $this->version; ?>"></script>
    <script src="http://admin.activepublishing.fr?controller=active-wireframe&action=default-js"></script>
<?php } else { ?>
    <script src="/plugins/ActiveWireframe/static/js/pages/preview.js?v=<?= $this->version; ?>"></script>
<?php } ?>

<script>
    $(document).ready(function() {
        $('.w2p-full').parents('div[id="page"]').css("padding", "0 !important")
            .find('.box-w2p')
            .removeClass('box-w2p')
            .addClass('box-w2p-full');
    });
</script>
