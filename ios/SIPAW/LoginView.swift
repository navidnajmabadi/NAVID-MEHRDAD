import SwiftUI

struct LoginView: View {
    @EnvironmentObject var api: API
    @State private var email = ""
    @State private var password = ""
    @State private var busy = false
    @State private var error = ""
    @State private var showSignup = false
    @State private var showForgot = false

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 18) {
                Text("SI PAW").font(.system(size: 44, weight: .heavy)).foregroundStyle(Color.accentColor).padding(.top, 40)
                Text("Welcome back").font(.title.bold())
                Text("SIPO has been keeping an eye on things. Sign in to catch up.").foregroundStyle(.secondary)

                VStack(alignment: .leading, spacing: 6) {
                    Text("Email").font(.footnote.weight(.semibold))
                    TextField("you@example.com", text: $email)
                        .textContentType(.emailAddress).keyboardType(.emailAddress).textInputAutocapitalization(.never).autocorrectionDisabled()
                        .padding(12).overlay(Rectangle().stroke(.separator))
                }
                VStack(alignment: .leading, spacing: 6) {
                    Text("Password").font(.footnote.weight(.semibold))
                    SecureField("Password", text: $password)
                        .textContentType(.password)
                        .padding(12).overlay(Rectangle().stroke(.separator))
                }
                if !error.isEmpty { Text(error).foregroundStyle(.red).font(.footnote) }

                Button {
                    Task { await signIn() }
                } label: {
                    HStack { if busy { ProgressView().tint(.white) }; Text("Log in").bold() }
                        .frame(maxWidth: .infinity, minHeight: 50)
                        .background(Color.accentColor).foregroundStyle(.white)
                }
                .disabled(busy || email.isEmpty || password.isEmpty)

                HStack {
                    Button("Forgot password?") { showForgot = true }
                    Spacer()
                    Button("Create an account") { showSignup = true }
                }.font(.footnote)
            }
            .padding(.horizontal, 24)
        }
        .sheet(isPresented: $showSignup) { SafariSheet(url: api.url("signup.php")) }
        .sheet(isPresented: $showForgot) { SafariSheet(url: api.url("forgot-password.php")) }
    }

    private func signIn() async {
        busy = true; error = ""
        defer { busy = false }
        do { try await api.login(email: email.trimmingCharacters(in: .whitespaces), password: password) }
        catch { self.error = error.localizedDescription }
    }
}
