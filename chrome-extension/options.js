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
	const deviceId = await getDeviceId();
	console.log("Device ID:", deviceId);

	loadFormData();

	document.getElementById("accountForm").addEventListener("submit", saveFormData);
	document.getElementById("registerDeviceForm").addEventListener("submit", registerDevice);
	document.getElementById("showRegisterDevice").addEventListener("click", () => {
		document.getElementById("deviceRegistration").classList.remove("hidden");
	});

	document.querySelectorAll(".tablinks").forEach(button => {
		button.addEventListener("click", (event) => {
			const tabName = event.target.getAttribute("data-tab");
			if (tabName) openTab(tabName);
		});
	});

	openTab("Account");
	document.getElementById("btnFetch").addEventListener("click", testLogin);
}

function loadFormData() {
	chrome.storage.sync.get(["user_mail"], function (items) {
		if (items.user_mail) {
			document.getElementById("user_mail").value = items.user_mail;
		}
	});
}

function saveFormData(e) {
	e.preventDefault();
	const user_mail = document.getElementById("user_mail").value;
	if (!user_mail) {
		alert("Veuillez entrer une adresse email.");
		return;
	}
	chrome.storage.sync.set({ user_mail: user_mail }, () => {
		alert("Email sauvegardé. Vous pouvez maintenant enregistrer l'appareil.");
	});
}

async function registerDevice(e) {
	e.preventDefault();

	const user_mail = document.getElementById("user_mail").value;
	const user_password = document.getElementById("user_password").value;
	const user_key = document.getElementById("user_key_register").value;
	const deviceId = await getDeviceId();

	if (!user_mail || !user_password || !user_key) {
		alert("Veuillez remplir email, mot de passe et clé secrète.");
		return;
	}

	const cryptoKey = await deriveKeyFromSecret(user_key);
	const encryptedPassword = await encryptValue(user_password, cryptoKey);

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
		.then(async res => {
			const data = await res.json();
			if (!res.ok) throw new Error(data.error || `Erreur HTTP: ${res.status}`);
			return data;
		})
		.then(data => {
			// Sauvegarde sécurisée du mot de passe chiffré et de la clé
			chrome.storage.sync.set({ user_password: encryptedPassword });
			chrome.storage.local.set({ user_key: user_key });

			alert("Appareil enregistré avec succès !");
			document.getElementById("deviceRegistration").classList.add("hidden");
		})
		.catch(err => {
			alert(`Erreur lors de l'enregistrement: ${err.message}`);
		});
}

async function testLogin() {
	const jsonResult = document.getElementById("jsonResult");
	jsonResult.textContent = "Chargement...";

	const deviceId = await getDeviceId();
	const { user_mail, user_password } = await new Promise(resolve => chrome.storage.sync.get(resolve));
	const { user_key } = await new Promise(resolve => chrome.storage.local.get(resolve));

	if (!user_mail || !user_password) {
		jsonResult.textContent = "Veuillez enregistrer l'appareil d'abord.";
		return;
	}
	if (!user_key) {
		jsonResult.textContent = "Clé secrète non trouvée. Veuillez ré-enregistrer l'appareil.";
		return;
	}

	const formData = new FormData();
	formData.append("action", "login");
	formData.append("user_mail", user_mail);
	formData.append("user_password", user_password);
	formData.append("device_id", deviceId);

	fetch(APPURL, {
		method: "POST",
		body: formData,
		headers: { "X-Addon-Key": EXPECTED_HEADER },
	})
		.then(async res => {
			const data = await res.json();
			if (!res.ok) throw new Error(data.error || `Erreur HTTP: ${res.status}`);
			return data;
		})
		.then(async data => {
			const cryptoKey = await deriveKeyFromSecret(user_key);
			const dechiffre = {};
			for (const k in data) {
				if (data.hasOwnProperty(k)) {
					dechiffre[k] = await decryptValue(data[k], cryptoKey);
				}
			}
			jsonResult.textContent = JSON.stringify(dechiffre, null, 2);
		})
		.catch(err => {
			jsonResult.textContent = `Erreur: ${err.message}`;
		});
}

// ----- Fonctions de chiffrement (inchangées) -----

async function deriveKeyFromSecret(secret) {
	const enc = new TextEncoder();
	const secretBytes = enc.encode(secret);
	const hash = await crypto.subtle.digest("SHA-256", secretBytes);
	return crypto.subtle.importKey("raw", hash, { name: "AES-CBC" }, false, ["encrypt", "decrypt"]);
}

async function encryptValue(str, cryptoKey) {
	const enc = new TextEncoder();
	const plainBytes = enc.encode(str);
	const iv = crypto.getRandomValues(new Uint8Array(16));
	const encrypted = await crypto.subtle.encrypt({ name: "AES-CBC", iv: iv }, cryptoKey, plainBytes);
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
	const decrypted = await crypto.subtle.decrypt({ name: "AES-CBC", iv: iv }, cryptoKey, ciphertext);
	const dec = new TextDecoder();
	return dec.decode(decrypted);
}
