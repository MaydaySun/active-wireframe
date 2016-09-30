<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width">

    <!-- Global CSS & JS -->
    <link href="/pimcore/static/css/fontello.css?v=<?= $this->version; ?>" rel="stylesheet">

    <link href="/plugins/ActiveWireframe/static/css/knacss.css?v=<?= $this->version; ?>" rel="stylesheet">
    <link href="/plugins/ActiveWireframe/static/css/knacss-extends.css?v=<?= $this->version; ?>" rel="stylesheet">
    <link href="/plugins/ActiveWireframe/static/css/layout.css?v=<?= $this->version; ?>" rel="stylesheet">

    <link href="/plugins/ActiveWireframe/static/css/icons.css" rel="stylesheet">
    <link href="/plugins/ActiveWireframe/static/css/reset-css.css" rel="stylesheet">

    <script src="/pimcore/static/js/pimcore/functions.js?v=<?= $this->version; ?>"></script>

    <!-- jQuery -->
    <script src="/plugins/ActiveWireframe/static/js/jquery-3.1.0.min.js"></script>
    <script src="/plugins/ActiveWireframe/static/jquery-ui-1.12.0/jquery-ui.js"></script>
    <link href="/plugins/ActiveWireframe/static/jquery-ui-1.12.0/jquery-ui.css" rel="stylesheet"/>
    <link href="/plugins/ActiveWireframe/static/jquery-ui-1.12.0/jquery-ui.theme.css" rel="stylesheet"/>

    <!-- jQuery plugins -->
    <script
        src="/plugins/ActiveWireframe/static/jquery-plugins/jquery-ui-rotatable/jquery.ui.rotatable.min.js"></script>
    <script src="/plugins/ActiveWireframe/static/jquery-plugins/jquery.scrollUp/jquery.scrollUp.js"></script>
    <script src="/plugins/ActiveWireframe/static/js/jquery-plugins.js?v=<?= $this->version; ?>"></script>

    <!-- Editmode -->
    <?php if ($this->editmode) { ?>
        <script src="/plugins/ActiveWireframe/static/js/menu.js?v=<?= $this->version; ?>"></script>
    <?php } ?>

</head>
<body id="activeWireframe" class="<?= ($this->editmode) ? 'is-hidden' : ''; ?>">

<!-- Document ID-->
<input id="documentId" type="hidden" value="<?= $this->documentId ?>"/>

<!-- Nav Pages-->
<?php if ($this->editmode && $this->document->getAction() == 'pages') { ?>

    <nav id="header-wraptop">

        <ul>
            <!--Zoom-->
            <li id="indicatorZoom" data-scale="1" class="navbar-text">
                <?php echo 100; ?>&percnt;
            </li>

            <li id="zoomUp">
                <a class="icon-zoom-in"></a>
                <span><?= $this->ts('active_wireframe_zoom_up'); ?></span>
            </li>

            <li id="zoomDown">
                <a class="icon-zoom-out"></a>
                <span><?= $this->ts('active_wireframe_zoom_down'); ?></span>
            </li>

            <!--Régles-->
            <li class="page-target" data-page-target="0">
                <a class="icon-target"></a>
                <span data-target="0"><?= $this->ts('active_wireframe_hide_guides'); ?></span>
                <span data-target="1" class="is-hidden"><?= $this->ts('active_wireframe_show_guides'); ?></span>
            </li>

            <?php if ($this->pageLock) { ?>
                <li class="navbar-text icon-ok document-text-lock">
                    <?= $this->ts('active_wireframe_document_validated'); ?>
                </li>
            <?php } ?>
        </ul>
    </nav>
    <div class="header-wraptop-ghost"></div>

<?php } elseif ($this->editmode && $this->document->getAction() == 'tree') { ?>

    <!--Nav Catalogue et Chapitre-->
    <nav id="header-wraptop">

        <ul>
            <!-- Génération du catalogue + aperçu-->
            <li id="reload-catalog">
                <a class="icon-picture"></a>
                <span><?= $this->ts('active_wireframe_new_thumbnail'); ?></span>
            </li>

            <!-- Génération d'une numérotation -->
            <li id="btn-pagination">
                <a class="icon-list-numbered"></a>
                <span><?= $this->ts('active_wireframe_new_paginator'); ?></span>
            </li>

            <li class="separator"></li>

            <!-- Différente position des pages -->
            <li class="position-thumbs" data-position="position-icon-address">
                <a class="icon-columns"></a>
                <span><?= $this->ts('active_wireframe_view_indesign'); ?></span>
            </li>

            <li class="position-thumbs position-first" data-position="position-icon-book-open">
                <a class="icon-book"></a>
                <span><?= $this->ts('active_wireframe_view_catalog'); ?></span>
            </li>

            <li class="position-thumbs" data-position="position-icon-th">
                <a class="icon-th"></a>
                <span><?= $this->ts('active_wireframe_view_thumb'); ?></span>
            </li>

            <!-- Page vérouillé -->
            <?php if ($this->pageLock) { ?>
                <li>
                <span class="icon-ok document-text-lock">
                <?= $this->ts('active_wireframe_document_validated'); ?>
            </span>
                </li>
            <?php } ?>

            <!-- Informations sur le catalogue -->
            <li class="navbar-text fr"><?= $this->informationsPage; ?></li>

        </ul>

    </nav>
    <div class="header-wraptop-ghost"></div>

    <div id="dialog-form-pagination">
        <form>
            <input id="input-index-paginator" type="number" min="0" name='index'
                   placeholder="<?= $this->ts('active_wireframe_number_first_page'); ?>"/>
        </form>
    </div>

<?php } ?>

<!-- content -->
<?= $this->layout()->content; ?>

<?php if ($this->editmode) { ?>
    <script type="text/javascript">
        $(document).ready(function () {
            $("body").fadeIn(1500);
        })
    </script>
<?php } ?>

</body>
</html>