$(document).ready(function () {
	$('#solid-enable-user-subdomains').change(function (el) {
		OCP.AppConfig.setValue('solid', 'userSubDomainsEnabled', this.checked ? true : false)
	})

	$('#solid-private-key').change(function(el) {
		OCP.AppConfig.setValue('solid', 'privateKey', this.value);
	});

	$('#solid-encryption-key').change(function(el) {
		OCP.AppConfig.setValue('solid', 'encryptionKey', this.value);
	});
	
	$('.solid-client-block').change(function(el) {
		let blocked = this.checked ? true : false;
		let keyName = 'client-' + this.getAttribute("data-client");
		let clientConfig = OCP.AppConfig.getValue('solid', keyName, "{}", {
			"success" : function(xmlData) {
				let clientJson = xmlData.querySelector('data > data').textContent;
				let clientConfig = JSON.parse(clientJson);
				clientConfig.blocked = blocked;
				OCP.AppConfig.setValue('solid', keyName, JSON.stringify(clientConfig));
			}
		});
	});
});