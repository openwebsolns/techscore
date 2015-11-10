/**
 * Form input for images with auto-preview.
 *
 * @param rootElement the container element.
 */
function ImageInputWithPreview(rootElement) {
    this.rootElement = rootElement;
    this.inputElement = this.rootElement.getElementsByTagName("input")[0];
    this.previewElement = this.rootElement.getElementsByTagName("img")[0];

    if (!this.inputElement || !this.previewElement || !FileReader) {
        // Nothing to do.
        return;
    }

    var myObj = this;
    this.inputElement.addEventListener(
        'change',
        function(e) {
            myObj.updatePreview();
        },
        false
    );
}

ImageInputWithPreview.prototype.updatePreview = function() {
    var myObj = this;
    var fr = new FileReader();
    fr.addEventListener(
        'load',
        function(e) {
            myObj.previewElement.src = fr.result;
        },
        false
    );
    fr.readAsDataURL(this.inputElement.files[0]);
};
