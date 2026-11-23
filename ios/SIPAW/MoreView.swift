import SwiftUI
import StoreKit

struct MoreView: View {
    @EnvironmentObject var api: API

    var body: some View {
        NavigationStack {
            List {
                Section("Your plan") {
                    NavigationLink { PlusView() } label: {
                        HStack {
                            Label("SIPAW Plus", systemImage: "star.fill")
                            Spacer()
                            Text(api.planLabel).foregroundStyle(.secondary)
                        }
                    }
                }
                Section("SIPAW") {
                    link("Lost pet", "lost.php", "location.magnifyingglass")
                    link("Walks and daily life", "daily.php", "figure.walk")
                    link("Expenses", "finance.php", "creditcard")
                    link("Rewards", "rewards.php", "pawprint.fill")
                    link("Take the tour", "tour.php", "play.circle")
                }
                Section("Account") {
                    link("Settings", "settings.php", "gearshape")
                    link("Help and support", "support.php", "questionmark.bubble")
                    link("Delete my account", "account-delete.php", "trash")
                    Button(role: .destructive) { Task { await api.logout() } } label: { Label("Sign out", systemImage: "rectangle.portrait.and.arrow.right") }
                }
            }
            .navigationTitle("More")
        }
    }

    private func link(_ title: String, _ path: String, _ icon: String) -> some View {
        NavigationLink { WebScreen(path: path, title: title) } label: { Label(title, systemImage: icon) }
    }
}

/// Plus, bought with Apple In-App Purchase.
struct PlusView: View {
    @EnvironmentObject var api: API
    @EnvironmentObject var store: StoreManager
    @Environment(\.openURL) private var openURL

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                Text("SIPAW Plus").font(.largeTitle.bold())
                Text("Everything in SIPAW for your pets: SIPO without limits, up to 3 pets, health and lab results, routines, walks, vet links and Lost pet alerts.").foregroundStyle(.secondary)

                if api.paidPlus {
                    VStack(alignment: .leading, spacing: 6) {
                        Label("Plus is active", systemImage: "checkmark.seal.fill").font(.headline).foregroundStyle(Color.accentColor)
                        if let e = api.planExpires { Text("Runs until \(e.prefix(10)). It extends by itself each time Apple renews it.").font(.footnote).foregroundStyle(.secondary) }
                    }.padding().frame(maxWidth: .infinity, alignment: .leading).overlay(Rectangle().stroke(Color.accentColor))
                }

                if let p = store.product {
                    VStack(alignment: .leading, spacing: 4) {
                        Text(p.displayName).font(.headline)
                        Text("\(p.displayPrice) per month").font(.title2.bold())
                        Text("Billed to your Apple ID every month until you cancel.").font(.footnote).foregroundStyle(.secondary)
                    }
                    Button { Task { await store.buy() } } label: {
                        HStack { if store.busy { ProgressView().tint(.white) }; Text(api.paidPlus ? "Extend with Apple" : "Subscribe").bold() }
                            .frame(maxWidth: .infinity, minHeight: 52).background(Color.accentColor).foregroundStyle(.white)
                    }.disabled(store.busy)
                } else if store.message.isEmpty {
                    ProgressView()
                }

                if !store.message.isEmpty { Text(store.message).font(.footnote).foregroundStyle(store.failed ? .red : .primary) }

                Button("Restore purchases") { Task { await store.restore() } }.font(.subheadline).disabled(store.busy)
                Button("Manage or cancel subscription") { openURL(URL(string: "https://apps.apple.com/account/subscriptions")!) }.font(.subheadline)

                Text("Payment is charged to your Apple ID at confirmation of purchase. The subscription renews automatically each month unless it is canceled at least 24 hours before the end of the current period. You can manage or cancel it in Settings, your name, Subscriptions. Prices include any taxes Apple applies in your country.")
                    .font(.caption).foregroundStyle(.secondary)
                HStack(spacing: 18) {
                    Link("Terms of Use", destination: URL(string: "https://www.apple.com/legal/internet-services/itunes/dev/stdeula/")!)
                    Link("Privacy Policy", destination: api.url("legal.php"))
                }.font(.footnote)
            }
            .padding(20)
        }
        .navigationBarTitleDisplayMode(.inline)
        .task { if store.product == nil { await store.loadProduct() } }
    }
}
