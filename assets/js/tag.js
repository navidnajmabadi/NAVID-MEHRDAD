(function () {
  "use strict";

  var cfg = window.RRP_QR || {};
  var host = document.getElementById("qrcode");
  if (!host || !cfg.url || typeof QRCode === "undefined") return;

  var qr = new QRCode(host, {
    text: cfg.url,
    width: 240,
    height: 240,
    correctLevel: QRCode.CorrectLevel.M,
  });

  function qrCanvas() {
    return host.querySelector("canvas") || host.querySelector("img");
  }

  function canvasFromQr() {
    var el = qrCanvas();
    if (!el) return null;
    if (el.tagName === "CANVAS") return el;
    var c = document.createElement("canvas");
    c.width = el.naturalWidth || 240;
    c.height = el.naturalHeight || 240;
    var ctx = c.getContext("2d");
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.drawImage(el, 0, 0);
    return c;
  }

  var pngBtn = document.getElementById("downloadPng");
  if (pngBtn) {
    pngBtn.addEventListener("click", function () {
      var canvas = canvasFromQr();
      if (!canvas) return;
      var a = document.createElement("a");
      a.download = (cfg.petName || "pet") + "-ringringpaw-qr.png";
      a.href = canvas.toDataURL("image/png");
      a.click();
    });
  }

  var pdfBtn = document.getElementById("downloadPdf");
  if (pdfBtn) {
    pdfBtn.addEventListener("click", function () {
      var canvas = canvasFromQr();
      if (!canvas) return;
      var jsPDF = (window.jspdf && window.jspdf.jsPDF) || window.jsPDF;
      if (!jsPDF) {
        window.print();
        return;
      }
      var doc = new jsPDF({ unit: "mm", format: "a6" });
      doc.setFontSize(14);
      doc.text("Ring Ring Paw", 15, 18);
      doc.setFontSize(12);
      doc.text((cfg.petName || "Pet") + " — scan to call owner", 15, 26);
      var img = canvas.toDataURL("image/png");
      doc.addImage(img, "PNG", 25, 35, 55, 55);
      doc.setFontSize(8);
      doc.text(cfg.url || "", 15, 100, { maxWidth: 75 });
      doc.save((cfg.petName || "pet") + "-ringringpaw-qr.pdf");
    });
  }

  // Silence unused qr lint in some tools
  void qr;
})();
