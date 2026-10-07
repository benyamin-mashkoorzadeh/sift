(function () {
  "use strict";

  var script = document.currentScript;
  if (!(script instanceof HTMLScriptElement)) return;

  var widgetKey = script.getAttribute("data-widget-key");
  if (!widgetKey || !/^sift_w_[A-Za-z0-9_-]{43}$/.test(widgetKey)) return;
  if (document.querySelector("iframe[data-sift-widget-frame]")) return;

  var scriptUrl;
  try {
    scriptUrl = new URL(script.src, document.baseURI);
  } catch {
    return;
  }

  if (scriptUrl.protocol !== "https:" && scriptUrl.protocol !== "http:") return;

  var trustedOrigin = scriptUrl.origin;
  var frame = document.createElement("iframe");
  var state = "launcher";

  frame.setAttribute("data-sift-widget-frame", "");
  frame.setAttribute("title", "Customer support");
  frame.setAttribute("aria-label", "Customer support");
  frame.src = trustedOrigin + "/widget/" + encodeURIComponent(widgetKey)
    + "#parent-origin=" + encodeURIComponent(window.location.origin);
  frame.style.position = "fixed";
  frame.style.zIndex = "2147483000";
  frame.style.border = "0";
  frame.style.background = "transparent";
  frame.style.colorScheme = "light";
  frame.style.overflow = "hidden";
  frame.style.maxWidth = "100vw";
  frame.style.maxHeight = "100dvh";

  function viewportSize() {
    return {
      width: window.visualViewport ? window.visualViewport.width : window.innerWidth,
      height: window.visualViewport ? window.visualViewport.height : window.innerHeight,
    };
  }

  function sizeFrame(nextState) {
    state = nextState;
    var viewport = viewportSize();

    if (state === "open") {
      if (viewport.width <= 480) {
        frame.style.width = Math.max(0, viewport.width - 16) + "px";
        frame.style.height = Math.max(0, viewport.height - 16) + "px";
        frame.style.right = "8px";
        frame.style.bottom = "8px";
      } else {
        frame.style.width = Math.min(380, viewport.width - 24) + "px";
        frame.style.height = Math.min(620, viewport.height - 24) + "px";
        frame.style.right = "12px";
        frame.style.bottom = "12px";
      }
      return;
    }

    frame.style.width = "64px";
    frame.style.height = "64px";
    frame.style.right = viewport.width <= 480 ? "10px" : "16px";
    frame.style.bottom = viewport.width <= 480 ? "10px" : "16px";
  }

  function isResizeMessage(value) {
    if (!value || typeof value !== "object") return false;
    var keys = Object.keys(value);

    return keys.length === 2
      && value.type === "sift:widget:resize"
      && (value.state === "launcher" || value.state === "open");
  }

  window.addEventListener("message", function (event) {
    if (event.origin !== trustedOrigin) return;
    if (event.source !== frame.contentWindow) return;
    if (!isResizeMessage(event.data)) return;

    sizeFrame(event.data.state);
  });

  window.addEventListener("resize", function () {
    sizeFrame(state);
  });
  if (window.visualViewport) {
    window.visualViewport.addEventListener("resize", function () {
      sizeFrame(state);
    });
  }

  sizeFrame("launcher");
  if (document.body) {
    document.body.appendChild(frame);
  } else {
    window.addEventListener("DOMContentLoaded", function () {
      if (!frame.isConnected && document.body) document.body.appendChild(frame);
    }, { once: true });
  }
})();
