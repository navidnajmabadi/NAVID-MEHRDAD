(function () {
  "use strict";

  var typeEl = document.getElementById("type");
  var customWrap = document.getElementById("customTypeWrap");
  var breedList = document.getElementById("breedList");
  var breeds = window.RRP_BREEDS || {};

  function refreshBreeds() {
    if (!typeEl || !breedList) return;
    var list = breeds[typeEl.value] || [];
    breedList.innerHTML = list
      .map(function (b) {
        return "<option value=\"" + b.replace(/"/g, "&quot;") + "\"></option>";
      })
      .join("");
    if (customWrap) {
      customWrap.style.display = typeEl.value === "custom" ? "" : "none";
    }
  }

  if (typeEl) {
    typeEl.addEventListener("change", refreshBreeds);
    refreshBreeds();
  }
})();
