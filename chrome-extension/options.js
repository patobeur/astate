// ----- gestion des onglets -----
function openTab(tabName) {
	// Get all elements with class="tabcontent" and hide them
	const tabcontent = document.querySelectorAll(".tabcontent");
	tabcontent.forEach((tab) => tab.classList.remove("active"));

	// Get all elements with class="tablinks" and remove the class "active"
	const tablinks = document.querySelectorAll(".tablinks");
	tablinks.forEach((link) => link.classList.remove("active"));

	// Show the current tab, and add an "active" class to the button that opened the tab
	const currentTab = document.getElementById(tabName);
	if (currentTab) {
		currentTab.classList.add("active");
	}

	const currentButton = document.querySelector(
		`.tablinks[data-tab='${tabName}']`
	);
	if (currentButton) {
		currentButton.classList.add("active");
	}
}

// ----- init au chargement -----
document.addEventListener("DOMContentLoaded", initOptions);

function initOptions() {
	// charge les données du formulaire
	loadFormData();

	// gestion du formulaire de compte
	var accountForm = document.getElementById("accountForm");
	if (accountForm) {
		accountForm.addEventListener("submit", saveFormData);
	}

	// gestion des onglets
	var tabButtons = document.querySelectorAll(".tablinks");
	tabButtons.forEach(function (button) {
		button.addEventListener("click", function (event) {
			const tabName = event.target.getAttribute("data-tab");
			if (tabName) {
				openTab(tabName);
			}
		});
	});

	// Activer le premier onglet par défaut
	openTab("General");

	var btnFetch = document.getElementById("btnFetch");
	var jsonResult = document.getElementById("jsonResult");

	if (btnFetch && jsonResult) {
		btnFetch.addEventListener("click", function () {
			jsonResult.textContent = "Chargement...";

			chrome.storage.sync.get(
				["login", "code", "encryptKey"],
				function (items) {
					if (!items.code || !items.login || !items.encryptKey) {
						jsonResult.textContent =
							"Veuillez remplir tous les champs du formulaire de compte.";
						return;
					}

					var formData = new FormData();
					formData.append("code", items.code);
					formData.append("login", items.login);

					fetch(APPURL, {
						method: "POST",
						body: formData,
						headers: {
							"X-Addon-Key": EXPECTED_HEADER, // même valeur que dans le PHP
						},
					})
						.then(function (res) {
							if (!res.ok) {
								throw new Error("Erreur HTTP : " + res.status);
							}
							return res.json();
						})
						.then(async function (data) {
							// ici data = { user: "base64...", role: "base64...", ... }

							var cryptoKey = await deriveKeyFromSecret(
								items.encryptKey
							);

							var dechiffre = {};
							for (var k in data) {
								if (data.hasOwnProperty(k)) {
									dechiffre[k] = await decryptValue(
										data[k],
										cryptoKey
									);
								}
							}

							// afficher le résultat clair
							jsonResult.textContent = JSON.stringify(
								dechiffre,
								null,
								2
							);
						})
						.catch(function (err) {
							jsonResult.textContent =
								"Erreur json : " + err.name + " - " + err.message;
							console.error(err);
						});
				}
			);
		});
	}
}

/**
 * charge les données depuis chrome.storage et remplit le formulaire
 */
function loadFormData() {
	chrome.storage.sync.get(["login", "code", "encryptKey"], function (items) {
		if (items.login) {
			document.getElementById("login").value = items.login;
		}
		if (items.code) {
			document.getElementById("code").value = items.code;
		}
		if (items.encryptKey) {
			document.getElementById("encryptKey").value = items.encryptKey;
		}
	});
}

/**
 * sauvegarde les données du formulaire dans chrome.storage
 */
function saveFormData(e) {
	e.preventDefault();

	var login = document.getElementById("login").value;
	var code = document.getElementById("code").value;
	var encryptKey = document.getElementById("encryptKey").value;

	chrome.storage.sync.set(
		{
			login: login,
			code: code,
			encryptKey: encryptKey,
		},
		function () {
			console.log("Données sauvegardées.");
		}
	);
}

/**
 * dérive la clé à partir du secret texte
 * => même chose que: hash('sha256', $key, true) en PHP
 */
async function deriveKeyFromSecret(secret) {
	var enc = new TextEncoder();
	var secretBytes = enc.encode(secret);

	// SHA-256 du secret
	var hash = await crypto.subtle.digest("SHA-256", secretBytes);

	// on importe cette clé pour AES-CBC
	return crypto.subtle.importKey("raw", hash, { name: "AES-CBC" }, false, [
		"decrypt",
	]);
}

/**
 * déchiffre une valeur envoyée par le PHP
 * le format est : base64( iv(16) + ciphertext )
 */
async function decryptValue(base64Data, cryptoKey) {
	// 1. base64 -> bytes
	var raw = atob(base64Data);
	var rawLen = raw.length;
	var rawBytes = new Uint8Array(rawLen);
	for (var i = 0; i < rawLen; i++) {
		rawBytes[i] = raw.charCodeAt(i);
	}

	// 2. séparer IV (16 octets) et ciphertext
	var iv = rawBytes.slice(0, 16);
	var ciphertext = rawBytes.slice(16);

	// 3. déchiffrer
	var decrypted = await crypto.subtle.decrypt(
		{
			name: "AES-CBC",
			iv: iv,
		},
		cryptoKey,
		ciphertext
	);

	// 4. bytes -> string
	var dec = new TextDecoder();
	return dec.decode(decrypted);
}
