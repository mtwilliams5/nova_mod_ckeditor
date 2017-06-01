/**
 * @license Copyright (c) 2003-2017, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */
	CKEDITOR.editorConfig = function( config ) {
		// Define changes to default configuration here.
		// For complete reference see:
		// http://docs.ckeditor.com/#!/api/CKEDITOR.config
	
		config.baseHref = '//example.com';
		config.basicEntities = false;
		config.bodyId = 'content-iframe';
		config.enterMode = CKEDITOR.ENTER_BR;
		config.fillEmptyBlocks = true;
		config.height = '30em';
		config.ignoreEmptyParagraph = true;
		config.language = 'en-gb';
		config.toolbar = [
			{ name: 'basic styles', items: [ 'Bold', 'Italic', 'Underline', 'Strike', '-', 'RemoveFormat' ] },
			{ name: 'clipboard', items: [ 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo' ] },
			{ name: 'editing', items: [ 'Scayt' ] },
			{ name: 'insert', items: [ 'HorizontalRule', 'SpecialChar' ] },
			{ name: 'tools', items: [ 'Maximise' ] },
			{ name: 'document', items: [ 'Source' ] }
		];
		config.removeButtons = 'Subscript,Superscript';
		config.removePlugins = 'about,blockquote,contextmenu,filebrowser,floatingspace,format,image,indentlist,link,list,showborders,stylescombo,table,tabletools';
		
		// Now let's set the CSS to use:
		config.contentsCss = '//example.com/application/views/_base_override/admin/css/contents.css';
		
	};
