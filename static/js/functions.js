/**
 * getParentByClass
 *  Récupère le premier parent d'un élément possédant la classe cls
 * @param element
 * @param cls
 * @returns {*}
 */
function getParentByClass(element, cls) {
    var parent = element.parentNode;
    var classes = parent.className.split(' ');
    if (classes.indexOf(cls) === -1) {
        parent = getParentByClass(parent, cls);
    }
    return parent;
}