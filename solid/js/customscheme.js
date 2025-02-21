console.log(document.location.href);
let newUrl = document.location.href.replace("customscheme", "authorize");
newUrl += "&customscheme=1";
document.location.href = newUrl;