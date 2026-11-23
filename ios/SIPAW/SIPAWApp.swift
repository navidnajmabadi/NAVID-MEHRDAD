import SwiftUI

@main
struct SIPAWApp: App {
    @StateObject private var api = API()
    @StateObject private var store = StoreManager()

    var body: some Scene {
        WindowGroup {
            RootView()
                .environmentObject(api)
                .environmentObject(store)
                .task {
                    await api.restore()
                    if api.loggedIn { store.start(api: api) }
                }
                .onChange(of: api.loggedIn) { _, now in
                    if now { store.start(api: api) }
                }
        }
    }
}

struct RootView: View {
    @EnvironmentObject var api: API

    var body: some View {
        if !api.checked {
            ProgressView().controlSize(.large)
        } else if api.loggedIn {
            MainTabs()
        } else {
            LoginView()
        }
    }
}

struct MainTabs: View {
    var body: some View {
        TabView {
            ChatView().tabItem { Label("SIPO", systemImage: "bubble.left.and.bubble.right.fill") }
            WebScreen(path: "dashboard.php").tabItem { Label("Home", systemImage: "house.fill") }
            WebScreen(path: "care.php").tabItem { Label("Health", systemImage: "heart.text.square.fill") }
            WebScreen(path: "routines.php").tabItem { Label("Routines", systemImage: "checklist") }
            MoreView().tabItem { Label("More", systemImage: "ellipsis.circle.fill") }
        }
    }
}
