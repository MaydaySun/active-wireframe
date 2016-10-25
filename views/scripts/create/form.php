<!-- CSS -->
<link href="/plugins/ActiveWireframe/static/css/create/form.css?v=<?= $this->version; ?>" rel="stylesheet">

<!-- Formulaire -->
<form id="form" method="post" enctype="multipart/form-data">

    <h1><?= $this->ts('active_wireframe_title_form'); ?></h1>

    <section>

        <div class="alert alert-danger">
            <strong><?= $this->ts('active_wireframe_information'); ?>&nbsp;:</strong>
            <?= $this->ts('active_wireframe_information_start'); ?>
        </div>

        <div class="tabs">

            <ul>
                <li><a id="nav-tabs-1" href="#tabs-1"><?= $this->ts('active_wireframe_configuration'); ?></a></li>
                <?php if ($this->templateFormPaginate) { ?>
                    <li>
                        <a id="nav-tabs-1bis" href="#tabs-1bis">
                            <?= $this->ts('active_paginate_import'); ?>
                        </a>
                    </li>
                <?php } ?>
                <li><a id="nav-tabs-2" href="#tabs-2"><?= $this->ts('active_wireframe_chapters_pages'); ?></a></li>
                <li><a id="nav-tabs-3" href="#tabs-3"><?= $this->ts('active_wireframe_personalization'); ?></a></li>
            </ul>

            <div id="tabs-1">

                <div class="alert alert-info">
                    <strong><?= $this->ts('active_wireframe_information') ?>&nbsp;:</strong>
                    <?= $this->ts('active_wireframe_information_configuration'); ?>
                </div>

                <div class="grid-2">

                    <!-- Format orientation couverture -->
                    <section>

                        <!-- Format de pages -->
                        <div>

                            <h6>
                                <span class="text-danger">*</span>
                                <?= $this->ts('active_wireframe_pages_format'); ?> :
                            </h6>

                            <select id="selectFormat" class="selectmenu" name="format">
                                <option value="840x1189" data-template="a0">A0 (840x1189 mm)</option>
                                <option value="594x841" data-template="a1">A1 (594x841 mm)</option>
                                <option value="420x594" data-template="a2">A2 (420x594 mm)</option>
                                <option value="297x420" data-template="a3">A3 (297x420 mm)</option>
                                <option value="210x297" data-template="a4" selected>A4 (210x297 mm)</option>
                                <option value="148x210" data-template="a5">A5 (148x210 mm)</option>
                                <option value="105x148" data-template="a6">A6 (105x148 mm)</option>
                                <option value="74x105" data-template="a7">A7 (74x105 mm)</option>
                                <option value="52x74" data-template="a8">A8 (52x74 mm)</option>
                                <option value="null" data-template="other">
                                    <?= $this->ts('active_wireframe_other...'); ?>
                                </option>
                            </select>

                            <!-- Format manuel -->
                            <div id="formatMan" class="is-hidden">
                                <h6>
                                    <span class="text-danger">*</span>
                                    <?= $this->ts('active_wireframe_format_personalize'); ?> :
                                </h6>
                                <input type="number" min="0" value="" name="format-width"
                                       placeholder="<?= $this->ts('active_wireframe_width'); ?>"/>
                                <span>(mm)</span>
                                <input type="number" min="0" value="" name="format-height"
                                       placeholder="<?= $this->ts('active_wireframe_height'); ?>"/>
                                <span>(mm)</span>
                            </div>
                        </div>

                        <!-- Orientation -->
                        <div>

                            <h6>
                                <span class="text-danger">*</span>
                                <?= $this->ts('active_wireframe_orientation'); ?> :
                            </h6>

                            <select name="orientation" id="selectOrientation" class="selectmenu" required>
                                <option value="portrait" data-o="portrait">
                                    <?= $this->ts('active_wireframe_portrait'); ?>
                                </option>
                                <option value="landscape" data-o="lansdscape">
                                    <?= $this->ts('active_wireframe_landscape'); ?>
                                </option>
                                <option value="auto" data-o="auto">Auto</option>
                            </select>
                        </div>

                        <!-- couvertures -->
                        <div>

                            <h6><?= $this->ts('active_wireframe_add_coverage'); ?></h6>

                            <div class="grid-4">

                                <label for="coverPage1">
                                    <input id="coverPage1" class="radioset" type="radio" name="coverPage" value="1"
                                           checked required/>
                                    <span><?= $this->ts('active_wireframe_yes'); ?></span>
                                </label for="coverPage0">

                                <label>
                                    <input id="coverPage0" class="radioset" type="radio" name="coverPage" required/>
                                    <span><?= $this->ts('active_wireframe_no'); ?></span>
                                </label>

                            </div>

                        </div>

                    </section>

                    <!-- Marges -->
                    <section id="pagesMargin">
                        <h6><?= $this->ts('active_wireframe_margins') ?> :</h6>

                        <div class="grid-2">
                            <input type="number" min="0" name="margin-little"
                                   placeholder="<?= $this->ts('active_wireframe_inner_margin'); ?>"/>

                            <input type="number" min="0" name="margin-great"
                                   placeholder="<?= $this->ts('active_wireframe_outer_margin'); ?>"/>

                            <input type="number" min="0" name="margin-top"
                                   placeholder="<?= $this->ts('active_wireframe_top'); ?>"/>

                            <input type="number" min="0" name="margin-bottom"
                                   placeholder="<?= $this->ts('active_wireframe_bottom'); ?>"/>
                        </div>
                    </section>

                </div>

                <?php if ($this->templateFormPaginate) { ?>
                    <div class="areaBtn">
                        <button class="button btn-next btn-nav-role fr custom_pimcore_icon pimcore_icon_arrowright"
                                data-nav-id="tabs-1bis">
                            <?= $this->ts('active_wireframe_next'); ?>
                        </button>
                        <p class="row"></p>
                    </div>
                <?php } else { ?>
                    <div class="areaBtn">
                        <button class="button btn-next btn-nav-role fr custom_pimcore_icon pimcore_icon_arrowright"
                                data-nav-id="tabs-2">
                            <?= $this->ts('active_wireframe_next'); ?>
                        </button>
                        <p class="row"></p>
                    </div>
                <?php } ?>

            </div>

            <div id="tabs-1bis">
                <?php
                // Intégration ActivePaginate
                if ($this->templateFormPaginate) {
                    $this->template($this->templateFormPaginate);
                }
                ?>
            </div>

            <div id="tabs-2">

                <div class="alert alert-info">
                    <strong><?= $this->ts('active_wireframe_information') ?>&nbsp;:</strong>
                    <?= $this->ts('active_wireframe_information_chapters_pages'); ?>
                </div>

                <!-- Ajout de chapitre -->
                <div class="divAddChapter">

                    <div>
                        <h6><?= $this->ts('active_wireframe_chapters'); ?></h6>
                        <button class="button btn-action custom_pimcore_icon pimcore_icon_plus"
                                data-btn-container="chapter">
                            <?= $this->ts('active_wireframe_add_chapter'); ?>
                        </button>
                    </div>

                    <article>

                        <div class="grid-3">
                            <label>
                                <?= $this->ts('active_wireframe_wording') ?> :
                                <br/>
                                <input type="text" value="" name="chapters[]">
                            </label>

                            <label>
                                <?= $this->ts('active_wireframe_number_of_pages'); ?> :
                                <br/>
                                <input type="number" min="0" value="0" name="numbPages[]">
                            </label>
                        </div>

                        <span class="close-page-chap icon-cancel-circled transition"></span>

                    </article>

                </div>

                <!-- Ajout de page -->
                <div class="divAddPages">

                    <div>
                        <h6><?= $this->ts('active_wireframe_offline_pages_chapters'); ?></h6>
                        <button class="button btn-action custom_pimcore_icon pimcore_icon_plus"
                                data-btn-container="page">
                            <?= $this->ts('active_wireframe_add_page'); ?>
                        </button>
                    </div>

                    <article>

                        <label>
                            <?= $this->ts('active_wireframe_wording') ?> :
                            <br/>
                            <input type="text" value="" name="pageStatic[]">
                        </label>

                        <span class="close-page-chap icon-cancel-circled transition"></span>

                    </article>

                </div>

                <?php if ($this->templateFormPaginate) { ?>

                    <div class="areaBtn">
                        <button class="button btn-back btn-nav-role fl custom_pimcore_icon pimcore_icon_arrowleft"
                                data-nav-id="tabs-1bis">
                            <?= $this->ts('active_wireframe_back'); ?>
                        </button>
                        <button class="button btn-next btn-nav-role fr custom_pimcore_icon pimcore_icon_arrowright"
                                data-nav-id="tabs-3">
                            <?= $this->ts('active_wireframe_next'); ?>
                        </button>
                        <p class="row"></p>
                    </div>

                <?php } else { ?>

                    <div class="areaBtn">
                        <button class="button btn-back btn-nav-role fl custom_pimcore_icon pimcore_icon_arrowleft"
                                data-nav-id="tabs-1">
                            <?= $this->ts('active_wireframe_back'); ?>
                        </button>
                        <button class="button btn-next btn-nav-role fr custom_pimcore_icon pimcore_icon_arrowright"
                                data-nav-id="tabs-3">
                            <?= $this->ts('active_wireframe_next'); ?>
                        </button>
                        <p class="row"></p>
                    </div>

                <?php } ?>

            </div>

            <div id="tabs-3">

                <div id="bloc-choose-template">

                    <div class="alert alert-info">
                        <strong><?= $this->ts('active_wireframe_information') ?>&nbsp;:</strong>
                        <?= $this->ts('active_wireframe_information_personalization'); ?>
                    </div>

                    <div class="alert alert-warning is-hidden">
                        <strong><?= $this->ts('active_wireframe_warning') ?>&nbsp;:</strong>
                        <?= $this->ts('active_wireframe_error_templates'); ?>
                    </div>

                    <section id="fieldThumbnails" class="grid-4"></section>
                </div>

                <div class="areaBtn">
                    <button class="button btn-back btn-nav-role fl custom_pimcore_icon pimcore_icon_arrowleft"
                            data-nav-id="tabs-2">
                        <?= $this->ts('active_wireframe_back'); ?>
                    </button>
                    <button id="btnSubmit" class="button fr custom_pimcore_icon pimcore_icon_checkbox">
                        <?= $this->ts('active_wireframe_assemble_catalog'); ?>
                    </button>
                    <p class="row"></p>
                </div>

            </div>

        </div>

    </section>


</form>

<!--JS-->
<script src="/plugins/ActiveWireframe/static/js/create/form.js?v=<?= $this->version; ?>"></script>
<?php if ($this->templateFormPaginate) { ?>
    <script src="/plugins/ActivePaginate/static/js/wireframe/form.js"></script>
<?php } ?>