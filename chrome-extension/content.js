(function () {
	const cssUrl = chrome.runtime.getURL("content.css");
	const link = document.createElement("link");
	link.href = cssUrl;
	link.rel = "stylesheet";
	document.head.appendChild(link);

	const div = document.createElement("div");
	div.className = "astate-injected-div";
	div.innerHTML = "Hello from the Astate extension!";
	document.body.appendChild(div);
})();
