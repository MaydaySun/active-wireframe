<style>
    #id-<?= $this->htmlId; ?> {
     /*...*/
    }
</style>

<!--
    Require: id="<?= $this->htmlId; ?>"
             class="box-w2p reset-css"
             data-o-id="<?= $this->object->getId(); ?>">
-->
<div id="id-<?= $this->htmlId; ?>" class="w2p-renderlet reset-css" data-o-id="<?= $this->object->getId(); ?>">
    <!-- Code Here -->

    <div class="w2p-element" data-element-key="1"> <!-- W2p element can be moved, resized ... -->
        <p>My Text</p>
    </div>

    <div class="w2p-element" data-element-key="2"> <!-- W2p element can be moved, resized ... -->
        <img src="..." alt="my img" />
    </div>

    <!-- End -->
</div>
