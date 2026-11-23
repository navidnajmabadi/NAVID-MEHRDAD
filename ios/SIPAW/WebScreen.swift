import SwiftUI
import WebKit
import SafariServices

/// A SIPAW web page inside the app, signed in with the same cookies as the native screens.
struct WebScreen: View {
    @EnvironmentObject var api: API
    let path: String
    var title: String? = nil

    var body: some View {
        WebContainer(url: api.url(path))
            .navigationTitle(title ?? "")
            .navigationBarTitleDisplayMode(.inline)
    }
}

struct WebContainer: UIViewRepresentable {
    let url: URL

    func makeUIView(context: Context) -> WKWebView {
        let cfg = WKWebViewConfiguration()
        cfg.websiteDataStore = .default()
        let web = WKWebView(frame: .zero, configuration: cfg)
        web.customUserAgent = Config.userAgent + " Mobile Safari"
        web.allowsBackForwardNavigationGestures = true
        web.isOpaque = false
        web.navigationDelegate = context.coordinator
        // Hand the native session over to the web view, then load the page.
        let store = cfg.websiteDataStore.httpCookieStore
        let cookies = (HTTPCookieStorage.shared.cookies ?? []).filter { $0.domain.contains(url.host ?? "") || (url.host ?? "").hasSuffix($0.domain) }
        let group = DispatchGroup()
        cookies.forEach { group.enter(); store.setCookie($0) { group.leave() } }
        group.notify(queue: .main) { web.load(URLRequest(url: url)) }
        return web
    }

    func updateUIView(_ uiView: WKWebView, context: Context) {}
    func makeCoordinator() -> Coordinator { Coordinator() }

    final class Coordinator: NSObject, WKNavigationDelegate {
        /// Pages of our own site stay in the app; other sites (vet links to maps, legal text) open in Safari.
        func webView(_ webView: WKWebView, decidePolicyFor action: WKNavigationAction, decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
            guard let url = action.request.url, action.navigationType == .linkActivated, let host = url.host else { decisionHandler(.allow); return }
            if host == Config.baseURL.host { decisionHandler(.allow) } else { UIApplication.shared.open(url); decisionHandler(.cancel) }
        }
    }
}

struct SafariSheet: UIViewControllerRepresentable {
    let url: URL
    func makeUIViewController(context: Context) -> SFSafariViewController { SFSafariViewController(url: url) }
    func updateUIViewController(_ vc: SFSafariViewController, context: Context) {}
}
