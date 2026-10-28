(function () {
  "use strict";

  var cfg = window.RRP_POSTER || {};
  var root = document.getElementById("lostPoster");
  if (!root) return;

  function capture() {
    if (typeof html2canvas !== "function") {
      window.alert("Poster library failed to load. Try again or use Print.");
      return null;
    }
    return html2canvas(root, {
      backgroundColor: "#ffffff",
      scale: 2,
      useCORS: true,
      logging: false,
      // html2canvas ignores object-fit and stretches the photo: swap it for a pre-cropped canvas.
      onclone: function (doc) {
        var src = root.querySelector(".pic img");
        var img = doc.querySelector("#lostPoster .pic img");
        if (!src || !img || !src.naturalWidth) return;
        var box = src.parentNode.getBoundingClientRect();
        var k = 2;
        var c = doc.createElement("canvas");
        c.width = Math.round(box.width * k);
        c.height = Math.round(box.height * k);
        c.style.width = box.width + "px";
        c.style.height = box.height + "px";
        c.style.display = "block";
        var nw = src.naturalWidth, nh = src.naturalHeight;
        var scale = Math.max(c.width / nw, c.height / nh); // cover
        var sw = c.width / scale, sh = c.height / scale;
        var sx = (nw - sw) / 2, sy = (nh - sh) / 2;
        c.getContext("2d").drawImage(src, sx, sy, sw, sh, 0, 0, c.width, c.height);
        img.parentNode.replaceChild(c, img);
      },
    });
  }

  var pngBtn = document.getElementById("posterPng");
  if (pngBtn) {
    pngBtn.addEventListener("click", function () {
      pngBtn.disabled = true;
      capture()
        .then(function (canvas) {
          if (!canvas) return;
          var a = document.createElement("a");
          a.download = (cfg.petName || "pet") + "-sipaw-lost-poster.png";
          a.href = canvas.toDataURL("image/png");
          a.click();
        })
        .catch(function () {
          window.alert("Could not create PNG.");
        })
        .finally(function () {
          pngBtn.disabled = false;
        });
    });
  }

  var pdfBtn = document.getElementById("posterPdf");
  if (pdfBtn) {
    pdfBtn.addEventListener("click", function () {
      pdfBtn.disabled = true;
      capture()
        .then(function (canvas) {
          if (!canvas) return;
          var jsPDF = (window.jspdf && window.jspdf.jsPDF) || window.jsPDF;
          if (!jsPDF) {
            window.print();
            return;
          }
          var doc = new jsPDF({ unit: "mm", format: "a4" });
          var pageW = doc.internal.pageSize.getWidth();
          var margin = 14;
          var maxW = pageW - margin * 2;
          var ratio = canvas.height / canvas.width;
          var w = maxW;
          var h = w * ratio;
          var img = canvas.toDataURL("image/png");
          doc.addImage(img, "PNG", margin, margin, w, h);
          doc.save((cfg.petName || "pet") + "-sipaw-lost-poster.pdf");
        })
        .catch(function () {
          window.alert("Could not create PDF.");
        })
        .finally(function () {
          pdfBtn.disabled = false;
        });
    });
  }
})();
