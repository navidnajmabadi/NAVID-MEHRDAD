import Foundation
import StoreKit

/// Plus bought with Apple In-App Purchase. Every transaction Apple signs is sent to our server, which verifies it
/// (Apple's certificate chain, our bundle and product, this person's account token) before it switches Plus on.
@MainActor
final class StoreManager: ObservableObject {
    @Published var product: Product?
    @Published var busy = false
    @Published var message = ""
    @Published var failed = false

    private var updates: Task<Void, Never>?
    private weak var api: API?

    func start(api: API) {
        self.api = api
        if updates == nil {
            // Renewals, purchases made on another device and "ask to buy" approvals arrive here, even after the app was closed.
            updates = Task { [weak self] in
                for await result in Transaction.updates {
                    await self?.handle(result)
                }
            }
        }
        Task { await loadProduct(); await syncEntitlements() }
    }

    func loadProduct() async {
        do {
            product = try await Product.products(for: [Config.plusProductID]).first
            if product == nil { say("The subscription is not available yet. Please try again later.", bad: true) }
        } catch {
            say("Could not reach the App Store. Check your connection.", bad: true)
        }
    }

    func buy() async {
        guard let api, let product else { return }
        guard let token = UUID(uuidString: api.accountToken) else {
            say("Please sign in again.", bad: true)
            return
        }
        busy = true
        defer { busy = false }
        do {
            let result = try await product.purchase(options: [.appAccountToken(token)])
            switch result {
            case .success(let verification):
                await handle(verification)
            case .pending:
                say("The purchase is waiting for approval. Plus turns on when it is approved.", bad: false)
            case .userCancelled:
                say("", bad: false)
            @unknown default:
                say("The purchase did not complete.", bad: true)
            }
        } catch {
            say("The purchase did not complete. You were not charged.", bad: true)
        }
    }

    /// Required by App Review: lets a person get Plus back on a new phone.
    func restore() async {
        busy = true
        defer { busy = false }
        do {
            try await AppStore.sync()
            await syncEntitlements()
            if message.isEmpty { say("Nothing to restore on this Apple ID.", bad: false) }
        } catch {
            say("Could not restore. Check your connection.", bad: true)
        }
    }

    /// Tells the server about every active subscription this Apple ID has (also run at launch).
    func syncEntitlements() async {
        var list: [String] = []
        for await result in Transaction.currentEntitlements {
            if case .verified(let t) = result, t.productID == Config.plusProductID { list.append(result.jwsRepresentation) }
        }
        guard !list.isEmpty else { return }
        if await send(list) { say("Plus is active.", bad: false) }
    }

    private func handle(_ result: VerificationResult<Transaction>) async {
        guard case .verified(let transaction) = result else {
            say("Apple could not verify this purchase.", bad: true)
            return
        }
        if await send([result.jwsRepresentation]) {
            await transaction.finish()   // only after the server has switched Plus on, so a failed call is retried
            say("Welcome to Plus! 🎉", bad: false)
        } else {
            say("The purchase went through but we could not activate Plus yet. Open this screen again, or press Restore.", bad: true)
        }
    }

    private func send(_ jws: [String]) async -> Bool {
        guard let api else { return false }
        do {
            let r = try await api.request("api/apple/sync.php", method: "POST", body: ["transactions": jws])
            if r.status == 200, r.json["ok"] as? Bool == true {
                _ = try? await api.loadMe()
                return true
            }
        } catch {}
        return false
    }

    private func say(_ text: String, bad: Bool) {
        message = text
        failed = bad
    }
}
