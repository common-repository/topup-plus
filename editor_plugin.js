(function() {
	// Load plugin specific language pack
	tinymce.PluginManager.requireLangPack('topupplus');
	
	tinymce.create('tinymce.plugins.topupplusPlugin', {
		init : function(ed, url) {
			var t = this;
			t.editor = ed;
			ed.addCommand('mce_topupplus', t._topupplus, t);
			ed.addButton('topupplus',{
				title : 'topupplus.desc', 
				cmd : 'mce_topupplus',
				image : url + '/images/topupplus-button.png'
			});
		},
		
		getInfo : function() {
			return {
				longname : 'TopUp Plus for Wordpress',
				author : 'Thorsten Puzich',
				authorurl : 'http://www.puzich.com',
				infourl : 'http://www.puzich.com/wordpress-plugins/topupplus-en/',
				version : '1.0'
			};
		},
		
		// Private methods
		_topupplus : function() { // open a popup window
			topupplus_insert();
			return true;
		}
	});

	// Register plugin
	tinymce.PluginManager.add('topupplus', tinymce.plugins.topupplusPlugin);
})();