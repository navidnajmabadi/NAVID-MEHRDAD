import Foundation
import SwiftUI

/// Where the app talks to. Debug builds use a local test server; release builds use the live site.
enum Config {
    #if DEBUG
    static let baseURL = URL(string: ProcessInfo.processInfo.environment["SIPAW_BASE_URL"] ?? "http://127.0.0.1:8099/")!
    #else
    static let baseURL = URL(string: "https://sipaw.nakilab.com/")!
    #endif
    /// The product created in App Store Connect (Subscriptions, group "SIPAW Plus").
    static let plusProductID = "com.nakilab.sipaw.plus.monthly"
    /// The web screens inside the app send this user agent, so the server hides its own (card) payment there.
    static let userAgent = "SIPAWApp/1.0 (iOS)"
}

struct APIError: LocalizedError {
    let message: String
    var errorDescription: String? { message }
}

/// Session with the SIPAW server: cookie login, CSRF token, the signed-in person's basics.
@MainActor
final class API: ObservableObject {
    @Published var checked = false
    @Published var loggedIn = false
    @Published var name = ""
    @Published var plan = "free"
    @Published var paidPlus = false
    @Published var accessState = ""
    @Published var trialDaysLeft: Int?
    @Published var planExpires: String?
    @Published var accountToken = ""

    private(set) var csrf = ""
    private let session: URLSession

    init() {
        let cfg = URLSessionConfiguration.default
        cfg.httpCookieStorage = HTTPCookieStorage.shared
        cfg.httpCookieAcceptPolicy = .always
        cfg.httpShouldSetCookies = true
        cfg.httpAdditionalHeaders = ["User-Agent": Config.userAgent, "Accept": "application/json"]
        session = URLSession(configuration: cfg)
    }

    func url(_ path: String) -> URL { URL(string: path, relativeTo: Config.baseURL)!.absoluteURL }

    /// One JSON request. POST bodies get the CSRF token and the phone's time zone added.
    @discardableResult
    func request(_ path: String, method: String = "GET", body: [String: Any]? = nil) async throws -> (status: Int, json: [String: Any]) {
        var req = URLRequest(url: url(path))
        req.httpMethod = method
        req.timeoutInterval = 60
        if var b = body {
            if !csrf.isEmpty { b["csrf"] = csrf }
            b["tz"] = TimeZone.current.identifier
            req.setValue("application/json", forHTTPHeaderField: "Content-Type")
            req.httpBody = try JSONSerialization.data(withJSONObject: b)
        }
        let (data, resp) = try await session.data(for: req)
        let status = (resp as? HTTPURLResponse)?.statusCode ?? 0
        let json = (try? JSONSerialization.jsonObject(with: data)) as? [String: Any] ?? [:]
        return (status, json)
    }

    /// Photo or PDF to SIPO (the server reads it and saves the numbers).
    func upload(data: Data, filename: String, mime: String, threadId: Int) async throws -> [String: Any] {
        let boundary = "sipaw-" + UUID().uuidString
        var req = URLRequest(url: url("api/si/upload.php"))
        req.httpMethod = "POST"
        req.timeoutInterval = 240
        req.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        var body = Data()
        func field(_ k: String, _ v: String) { body.append("--\(boundary)\r\nContent-Disposition: form-data; name=\"\(k)\"\r\n\r\n\(v)\r\n".data(using: .utf8)!) }
        field("csrf", csrf)
        field("thread_id", String(threadId))
        field("tz", TimeZone.current.identifier)
        body.append("--\(boundary)\r\nContent-Disposition: form-data; name=\"file\"; filename=\"\(filename)\"\r\nContent-Type: \(mime)\r\n\r\n".data(using: .utf8)!)
        body.append(data)
        body.append("\r\n--\(boundary)--\r\n".data(using: .utf8)!)
        req.httpBody = body
        let (d, _) = try await session.data(for: req)
        return (try? JSONSerialization.jsonObject(with: d)) as? [String: Any] ?? ["ok": false, "error": "The server did not answer properly."]
    }

    // MARK: sign in and out

    /// Called at launch: the remember-me cookie signs the person in again without a password.
    func restore() async {
        _ = try? await loadMe()
        checked = true
    }

    @discardableResult
    func loadMe() async throws -> Bool {
        let r = try await request("api/me.php")
        guard r.status == 200, r.json["ok"] as? Bool == true else {
            loggedIn = false
            return false
        }
        csrf = r.json["csrf"] as? String ?? csrf
        accountToken = r.json["apple_account_token"] as? String ?? ""
        planExpires = r.json["plan_expires_at"] as? String
        paidPlus = r.json["paid_plus"] as? Bool ?? false
        if let a = r.json["access"] as? [String: Any] { accessState = a["state"] as? String ?? ""; trialDaysLeft = a["days_left"] as? Int }
        if let o = r.json["owner"] as? [String: Any] {
            plan = o["plan"] as? String ?? "free"
            name = (o["name"] as? String) ?? ""
        }
        loggedIn = true
        return true
    }

    func login(email: String, password: String) async throws {
        let r = try await request("api/auth/login.php", method: "POST", body: ["email": email, "password": password])
        guard r.status == 200, r.json["ok"] as? Bool == true else {
            throw APIError(message: r.json["error"] as? String ?? "Could not sign in. Check your connection and try again.")
        }
        csrf = r.json["csrf"] as? String ?? ""
        _ = try await loadMe()
    }

    /// What to show next to "SIPAW Plus".
    var planLabel: String {
        if paidPlus { return "Active" }
        if accessState == "admin" { return "Team" }
        if accessState == "trial", let d = trialDaysLeft { return "Trial: \(d) \(d == 1 ? "day" : "days") left" }
        if accessState == "expired" { return "Trial ended" }
        return "Free"
    }

    func logout() async {
        _ = try? await request("logout.php")
        HTTPCookieStorage.shared.cookies?.forEach { HTTPCookieStorage.shared.deleteCookie($0) }
        csrf = ""; name = ""; plan = "free"; planExpires = nil; accountToken = ""; paidPlus = false
        loggedIn = false
    }
}
