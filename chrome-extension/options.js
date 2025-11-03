// ----- gestion des onglets -----
function openTab(tabName) {
	const tabcontent = document.querySelectorAll(".tabcontent");
	tabcontent.forEach((tab) => tab.classList.remove("active"));

	const tablinks = document.querySelectorAll(".tablinks");
	tablinks.forEach((link) => link.classList.remove("active"));

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

// ----- Device ID Management -----
async function getDeviceId() {
	return new Promise((resolve) => {
		chrome.storage.local.get("device_id", function (items) {
			if (items.device_id) {
				resolve(items.device_id);
			} else {
				const newDeviceId =
					"device_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9);
				chrome.storage.local.set({ device_id: newDeviceId }, function () {
					resolve(newDeviceId);
				});
			}
		});
	});
}

// ----- init au chargement -----
document.addEventListener("DOMContentLoaded", initOptions);

async function initOptions() {
	// Assurer qu'on a un device_id
	const deviceId = await getDeviceId();
	console.log("Device ID:", deviceId);

	// charge les données du formulaire
	loadFormData();

	// gestion du formulaire de compte
	var accountForm = document.getElementById("accountForm");
	if (accountForm) {
		accountForm.addEventListener("submit", saveFormData);
	}

	// gestion du formulaire d'enregistrement d'appareil
	var registerDeviceForm = document.getElementById("registerDeviceForm");
	if (registerDeviceForm) {
		registerDeviceForm.addEventListener("submit", registerDevice);
	}

	// gestion du bouton pour afficher le formulaire d'enregistrement
	var showRegisterDeviceBtn = document.getElementById("showRegisterDevice");
	if (showRegisterDeviceBtn) {
		showRegisterDeviceBtn.addEventListener("click", function () {
			document.getElementById("deviceRegistration").classList.remove("hidden");
		});
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
	if (btnFetch) {
		btnFetch.addEventListener("click", testLogin);
	}
}

/**
 * charge les données depuis chrome.storage et remplit le formulaire
 */
function loadFormData() {
	chrome.storage.sync.get(["user_mail"], function (items) {
		if (items.user_mail) {
			document.getElementById("user_mail").value = items.user_mail;
		}
		// On ne peut plus déchiffrer le mot de passe ici car on n'a pas la clé.
	});
}

/**
 * sauvegarde les données du formulaire dans chrome.storage
 */
async function saveFormData(e) {
	e.preventDefault();

	var user_mail = document.getElementById("user_mail").value;
	var user_password = document.getElementById("user_password").value;

	if (!user_mail || !user_password) {
		console.log("Veuillez remplir email et mot de passe.");
		return;
	}

	chrome.storage.sync.set(
		{
			user_mail: user_mail,
			// Le mot de passe sera chiffré lors de l'enregistrement de l'appareil
			user_password: user_password, // Stocké temporairement en clair
		},
		function () {
			console.log("Données de base sauvegardées. Pensez à enregistrer l'appareil.");
			alert("Données sauvegardées. Si c'est un nouvel appareil ou un nouveau mot de passe, n'oubliez pas d'enregistrer l'appareil.");
		}
	);
}


async function registerDevice(e) {
	e.preventDefault();

	const user_mail = document.getElementById("user_mail").value;
	const user_password = document.getElementById("user_password").value;
	const user_key = document.getElementById("user_key_register").value;
	const deviceId = await getDeviceId();

	if (!user_mail || !user_password || !user_key) {
		alert("Veuillez remplir tous les champs : email, mot de passe et clé secrète.");
		return;
	}

	// Chiffrer le mot de passe avec la clé secrète avant de l'envoyer
	const cryptoKey = await deriveKeyFromSecret(user_key);
	const encryptedPassword = await encryptValue(user_password, cryptoKey);

	// Sauvegarder le mot de passe chiffré pour les futurs logins
	chrome.storage.sync.set({ user_password: encryptedPassword });

	const formData = new FormData();
	formData.append("action", "register_device");
	formData.append("user_mail", user_mail);
	formData.append("user_password", encryptedPassword);
	formData.append("user_key", user_key);
	formData.append("device_id", deviceId);

	fetch(APPURL, {
		method: "POST",
		body: formData,
		headers: { "X-Addon-Key": EXPECTED_HEADER },
	})
		.then(function (res) {
			if (!res.ok) {
				throw new Error("Erreur HTTP : " + res.status);
			}
			return res.json();
		})
		.then(function (data) {
			console.log(data);
			alert("Appareil enregistré avec succès !");
			document.getElementById("deviceRegistration").classList.add("hidden");
		})
		.catch(function (err) {
			console.error(err);
			alert("Erreur lors de l'enregistrement de l'appareil.");
		});
}


async function testLogin() {
	const jsonResult = document.getElementById("jsonResult");
	jsonResult.textContent = "Chargement...";

	const deviceId = await getDeviceId();

	chrome.storage.sync.get(["user_mail", "user_password"], function (items) {
		if (!items.user_mail || !items.user_password) {
			jsonResult.textContent = "Veuillez d'abord sauvegarder vos informations de compte.";
			return;
		}

		const formData = new FormData();
		formData.append("action", "login");
		formData.append("user_mail", items.user_mail);
		formData.append("user_password", items.user_password); // déjà chiffré
		formData.append("device_id", deviceId);

		fetch(APPURL, {
			method: "POST",
			body: formData,
			headers: { "X-Addon-Key": EXPECTED_HEADER },
		})
			.then(res => res.json())
			.then(data => {
				// NOTE : La réponse `data` est chiffrée. Pour la déchiffrer ici,
				// il faudrait demander la user_key à l'utilisateur, ce qui va à l'encontre
				// de l'objectif. Le test de connexion se contente donc de vérifier
				// que la requête réussit et renvoie des données.
				jsonResult.textContent = "Réponse chiffrée reçue du serveur :\n" + JSON.stringify(data, null, 2);
			})
			.catch(err => {
				jsonResult.textContent = "Erreur : " + err.message;
				console.error(err);
			});
	});
}

// ----- Fonctions de chiffrement (inchangées) -----

async function deriveKeyFromSecret(secret) {
	const enc = new TextEncoder();
	const secretBytes = enc.encode(secret);
	const hash = await crypto.subtle.digest("SHA-256", secretBytes);
	return crypto.subtle.importKey("raw", hash, { name: "AES-CBC" }, false, [
		"encrypt",
		"decrypt",
	]);
}

async function encryptValue(str, cryptoKey) {
	const enc = new TextEncoder();
	const plainBytes = enc.encode(str);
	const iv = crypto.getRandomValues(new Uint8Array(16));
	const encrypted = await crypto.subtle.encrypt(
		{ name: "AES-CBC", iv: iv },
		cryptoKey,
		plainBytes
	);
	const resultBytes = new Uint8Array(iv.length + encrypted.byteLength);
	resultBytes.set(iv, 0);
	resultBytes.set(new Uint8Array(encrypted), iv.length);
	return btoa(String.fromCharCode.apply(null, resultBytes));
}

async function decryptValue(base64Data, cryptoKey) {
	const raw = atob(base64Data);
	const rawLen = raw.length;
	const rawBytes = new Uint8Array(rawLen);
	for (let i = 0; i < rawLen; i++) {
		rawBytes[i] = raw.charCodeAt(i);
	}
	const iv = rawBytes.slice(0, 16);
	const ciphertext = rawBytes.slice(16);
	const decrypted = await crypto.subtle.decrypt(
		{ name: "AES-CBC", iv: iv },
		cryptoKey,
		ciphertext
	);
	const dec = new TextDecoder();
	return dec.decode(decrypted);
}
