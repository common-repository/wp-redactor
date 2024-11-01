/*! Redactor 0.0.1 wp-redactor.js 2016-05-22 5:33:26 PM */
var redactorHelper = new function() {
    this.shortcodes2Html = function(content) {
        if (null == content) {
            return "";
        }
        return content.replace(/\[(redact[^\]]*)\]([^\[]*)\[\/redact\]/g, function(match, attributes, text) {
            return "<span class='allowed' title='" + attributes.replace(/"|'/g, "|") + "'>" + text + "</span>";
        });
    };
    this.html2Shortcodes = function(content) {
        if (null == content) {
            return "";
        }
        return content.replace(/<span class=\"allowed\" title=\"([^\"]+)\">([^<]*)<\/span>/g, function(match, attributes, text) {
            return "[" + attributes.replace(/\|/g, "'") + "]" + text + "[/redact]";
        });
    };
    this.replaceNodeWithFirstChild = function(node) {
        if (null == node) {
            return "";
        }
        var parent = node.parentNode;
        var text = node.firstChild;
        parent.replaceChild(text, node);
    };
}();

jQuery(document).ready(function() {
	
	if(jQuery.fn.tooltipster) {
	    jQuery(".redacted, .tooltip").tooltipster({
	        position: "top-right"
	    });
	}
    
    if(typeof spoilerAlert == 'function') {
    	spoilerAlert('spoiler');
    	spoilerAlert('.spoiler', {max: 5, partial: 2});
	}
    
    if(jQuery.fn.wpColorPicker) {
    	jQuery('.redactor-color-selector').wpColorPicker();
	}
});