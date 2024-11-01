/**
 * This is a custom plugin for TinyMCE that will add
 * the redact button to the editor and have it open
 * the redact dialog box.
 */
(function() {
     tinymce.create('tinymce.plugins.RedactPlugin', {
          init : function(editor, pluginUrl) {
        	  var mceWidgets = new Array();
        	  var username = "unknown";
        	  var date = "";

        	  mceWidgets[0] = {type:'label', text:'Select roles allowed to view redacted content.', multiline: true};
              mceWidgets[1] = {type:'label', text:'Administrators and editors can always view redacted text.', multiline: true};
              tinymce.util.XHR.send({
            	  url: ajaxurl + '?action=get_redact_dialog',
            	  success: function(response) {
            		  mceWidgets = tinymce.util.JSON.parse(response);
            	  }
              })
        	  
        	  /*
        	   * Get user name and today's date from server
        	   */
        	  tinymce.util.XHR.send({
        		  url : ajaxurl + '?action=get_username_date',
        		  success : function(response) {
        			  var info = tinymce.util.JSON.parse(response);
        			  if (info != null && info.name != null && info.name.trim().length > 0) {
        				  username = info.name.trim();
        			  }
        			  if (info != null && info.date != null && info.date.trim().length > 0) {
        				  date = info.date.trim();
        			  }
        		  }
        	  });
        	  
        	  /*
        	   * Right before the content is set into the editor, process all shortcodes almost as wordpress
        	   * would do so that it shows in the visual editor list it would in a post.
        	   */
			  editor.on('BeforeSetContent', function (event) {
				  event.content = redactorHelper.shortcodes2Html(event.content);
			  });
			  
			  /*
			   * GetContent is fired when switching to Text mode and also when saving, "unprocess" html back
			   * into shortcodes so that they can be edited and it saves to wp as shortcodes and not our
			   * html renderings.
			   */
			  editor.on('GetContent', function (event) {
				  event.content = redactorHelper.html2Shortcodes(event.content);
			  });

			  // Add the Redact button to the MCE button bar
			  editor.addButton( 'redact', {
        		  title : 'Redact',
        		  image: pluginUrl + '/../images/redact.png',
        		  onclick : function () {
        			  openDialog();
        		  }
        	  });
			  
			  // Add a double check event to open the dialog if user double clicks on a redaction
			  editor.on('DblClick', function(event) {
				 if (event.target.className == 'allowed') {
					 openDialog(event.target);
				 }
			  });

			  /**
			   * Open a dialog window to allow user to select roles that are permitted
			   * to read the redaction.
			   * @param spanNode Optional argument to specify the HTML node the cursor is in if nothing in the editor has been selected.
			   */
			  openDialog = function(spanNode) {
				  var content = editor.selection.getContent();
				  
				  // avoid putting in nested [redact] shortcodes by removing any existing in selection
				  // also remove empty <p/> tags
				  var text = content.replace(/\[redact[^\]]*\]|\[\/redact\]|<p>\s*<\/p>/g, ""); 
				  var outerRedaction = isInRedaction(editor.selection.getNode());
    			  if (outerRedaction !== false) {
    				  spanNode = editor.selection.getNode();
    			  }
				  
    			  var values = null;
    			  if (spanNode != null) {
    				  values = getPreviousChoices(spanNode);
    			  }
    			  
    			  console.log(values);
    			  
				  if (spanNode != null && values != null) {
					  // set the values from the existing shortcode attributes
					  mceWidgets.forEach( function (item) {
						  if(item.name == 'role') {
							  // since this is a button group we have to loop
							  // over the sub items
							  item.items.forEach(function(item) {
								  item.checked = (values[item.name] != null);
							  });
						  }
						  if(item.name == 'style') {
							  item.value = values.style;
						  }
					  });
				  }
    			  if (content.length == 0 && spanNode == null) {
    				  editor.windowManager.alert('Please highlight the text to be redacted first.');
    			  } else {
    				  editor.windowManager.open( {
    					  title: 'Redact',
    					  width: 450,
    					  height: 300,
    					  autoScroll: true,
    					  id: 'datasync_redaction_dialog',
    					  body: mceWidgets,
    					  buttons: [{text: 'Cancel', classes : 'widget btn first abs-layout-item', onclick: 'close'},
    					            {text: 'Remove', classes : 'widget btn first abs-layout-item', onclick: removeShortcode, disabled: spanNode == null}, 
    					            {text: 'Save', classes : 'widget btn primary first abs-layout-item', onclick: 'submit'}],
    					  onsubmit: function (e) {
    						  addShortcode(e, editor, spanNode, text)
    					  },
    					  onclose: resetDialog
    				  });
    			  };
			  }

			  /**
			   * Remove the redaction.
			   * @param e The "remove" button click event
			   */
			  removeShortcode = function (e) {
				  redactorHelper.replaceNodeWithFirstChild(editor.selection.getNode());
				  editor.windowManager.close();
			  };
			  
			  /**
			   * Submission event handler to add or edit shortcode
			   * @param e The mouse event
			   * @param editor The MCE editor
			   * @param spanNode The span node that is the HTML representation of our shortcode, this could be null
			   * @param text The user selected text to apply the shortcode around.
			   */
			  addShortcode = function (e, editor, spanNode, text) {
				  var roles = [];
				  var style = '';

				  // tinymce doesn't really support input groups so we
				  // are just going to parse the inputs looking for the
				  // checkboxes that look like role[administrator] = true
				  for (var param in e.data) {
					  var role = param.match(/role\[([a-z]+)\]/);
					  if( role && e.data[param] ) {
						  roles.push(role[1]);
					  }
					  if(param=='style') {
						  style = e.data['style'];
					  }
				  }
				  
				  // if the span doesn't exist yet we will create it
				  if (spanNode == null) {
					  var newContent = "[redact";
					  
					  if (roles.length > 0) {
						  newContent += " allow='" + roles.join() + "'";
					  }
					  
					  newContent += " redactor='" + username + "'";
					  if (date.length > 0) {
						  newContent += " date='" + date + "'";
					  }
					  
					  if( e.data.style && e.data.style != 'default' ) {
						  newContent += " style='"+ e.data.style +"'";						  
					  }
					  newContent += "]";

					  // TODO: WP will "help" you when the user selects between paragraph blocks
					  // even if you don't select the entire paragraph, it will terminate the
					  // block with a closing </p> for you in your selection content which will
					  // mean that you'll end up with a paragraph break in the middle of your
					  // sentence. And we don't want to strip it out because users may select
					  // an entire paragraph and you would have removed the paragraph blocks.
					  // It's going to look a little funky on the screen, but it's an easy fix
					  // for the user; albeit, annoying. We should address this at a later date.
					  if (text.match(/<p>|<\/p>/gi)) {
						  newContent = text.replace(/<p>/gi, "<p>"+newContent).replace(/<\/p>/gi, "[/redact]</p>");
					  } else {
						  newContent += text + "[/redact]";
					  }
					  editor.selection.setContent(newContent);
				  } else {
					  var title = spanNode.attributes["title"].value
					  title = title.replace(/\s?allow=\|[^|]+\|/g, "");
					  title = title.replace(/\s?style=\|[^|]+\|/g, "");
					  
					  if ( roles.length > 0 ) {
						  title += " allow=|" + roles.join()  + "|";
					  }
					  
					  if( style.length > 0) {
						  title += " style=|" + style + "|";
					  }
					  spanNode.attributes["title"].value = title;
				  }
			  };

			  /**
			   * Reset the checkboxes in our dialog to be unchecked
			   */
			  resetDialog = function () {
				  mceWidgets.forEach( function (item) {
					  if (item.type == 'checkbox') {
						  item.checked = false;
					  }
				  });
			  };
			  
			  /**
			   * Given a DOM node, return the redaction span node if parameter node is
			   * inside a rendered redaction; false otherwise.
			   */
			  isInRedaction = function (node) {
				  if (node == null) {
					  return false;
				  }
				  if (node.nodeName.match(/^span$/i) != null && node.className.match(/^allowed$/i) != null) {
					  return node;
				  } else {
					  return isInRedaction(node.parentNode);
				  }
			  };
			  
			  /**
			   * Parse the HTML element given for the selected roles written 
			   * into the title attribute.
			   */
			  getPreviousChoices = function (node) {
				  
				  var ret = {};
				  
				  var title = node.attributes["title"];
				  var previousChoices = null;
				  if (title != null) {
					  var data = title.value;
					  
					  // get the allowed roles
					  var allow = /allow=\|([^|]+)\|/g.exec(data);
					  if (allow != null) {
						  allow[1].split(',').forEach(function(item){
							 ret['role['+item+']'] = true; 
						  });
					  }
					  
					  // get the style
					  var style = /style=\|([a-z]*)\|/g.exec(data);
					  if(style != null) {
						  ret['style'] = style[1];
					  }
					  
				  }
				  return ret; 
			  }
          }
     });
     tinymce.PluginManager.add( 'redactor', tinymce.plugins.RedactPlugin );
})();
