import SwiftUI
import PhotosUI
import Combine

struct ChatMessage: Identifiable, Equatable {
    let id: Int
    let fromMe: Bool
    let body: String
    let choices: [String]
}

/// SIPO chat, native: the same server chat as the website (same memory, same pet), shown in a native screen.
struct ChatView: View {
    @EnvironmentObject var api: API
    @State private var messages: [ChatMessage] = []
    @State private var threadId = 0
    @State private var petName = ""
    @State private var text = ""
    @State private var sending = false
    @State private var note = ""
    @State private var picked: PhotosPickerItem?
    @State private var loaded = false
    private let poll = Timer.publish(every: 8, on: .main, in: .common).autoconnect()

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                ScrollViewReader { proxy in
                    ScrollView {
                        LazyVStack(spacing: 8) {
                            if !loaded { ProgressView().padding(.top, 40) }
                            ForEach(messages) { m in bubble(m).id(m.id) }
                            if sending { typing }
                        }
                        .padding(12)
                    }
                    .onChange(of: messages) { _, _ in
                        if let last = messages.last { withAnimation { proxy.scrollTo(last.id, anchor: .bottom) } }
                    }
                }
                if !note.isEmpty { Text(note).font(.footnote).foregroundStyle(.red).padding(.horizontal, 12) }
                composer
            }
            .navigationTitle(petName.isEmpty ? "SIPO" : "SIPO · \(petName)")
            .navigationBarTitleDisplayMode(.inline)
        }
        .task { await load() }
        .onReceive(poll) { _ in Task { await refresh() } }
        .onChange(of: picked) { _, item in
            guard let item else { return }
            Task { await upload(item) }
        }
    }

    private func bubble(_ m: ChatMessage) -> some View {
        VStack(alignment: m.fromMe ? .trailing : .leading, spacing: 6) {
            Text(m.body)
                .padding(.horizontal, 12).padding(.vertical, 9)
                .background(m.fromMe ? Color.accentColor : Color(.secondarySystemBackground))
                .foregroundStyle(m.fromMe ? Color.white : Color.primary)
                .clipShape(RoundedRectangle(cornerRadius: 14))
                .frame(maxWidth: 300, alignment: m.fromMe ? .trailing : .leading)
            if !m.fromMe, m.id == messages.last?.id, !m.choices.isEmpty {
                FlowButtons(items: m.choices) { c in Task { await send(c) } }
            }
        }
        .frame(maxWidth: .infinity, alignment: m.fromMe ? .trailing : .leading)
    }

    private var typing: some View {
        HStack { ProgressView(); Text("SIPO is thinking…").font(.footnote).foregroundStyle(.secondary); Spacer() }.padding(.horizontal, 6)
    }

    private var composer: some View {
        HStack(spacing: 8) {
            PhotosPicker(selection: $picked, matching: .images) {
                Image(systemName: "photo.badge.plus").font(.title3).frame(width: 40, height: 40)
            }
            .accessibilityLabel("Send a photo, for example lab results")
            TextField("Tell SIPO about \(petName.isEmpty ? "your pet" : petName)…", text: $text, axis: .vertical)
                .lineLimit(1...4)
                .padding(10).overlay(Rectangle().stroke(.separator))
            Button { Task { await send(text) } } label: {
                Image(systemName: "arrow.up").font(.headline).foregroundStyle(.white).frame(width: 40, height: 40).background(Color.accentColor)
            }
            .disabled(sending || text.trimmingCharacters(in: .whitespaces).isEmpty)
            .accessibilityLabel("Send")
        }
        .padding(10)
        .background(.bar)
    }

    // MARK: server

    private func present(_ list: [[String: Any]]) -> [ChatMessage] {
        list.compactMap { m in
            guard let id = m["id"] as? Int, let body = m["body"] as? String else { return nil }
            let choices = (m["choices"] as? [String]) ?? ((m["choices"] as? [[String: Any]])?.compactMap { $0["label"] as? String ?? $0["text"] as? String }) ?? []
            return ChatMessage(id: id, fromMe: (m["role"] as? String) == "user", body: body, choices: choices)
        }
    }

    private func load() async {
        defer { loaded = true }
        guard let r = try? await api.request("api/si/thread.php"), r.json["ok"] as? Bool == true else { note = "Could not load the chat. Check your connection."; return }
        if let t = r.json["thread"] as? [String: Any] {
            threadId = t["id"] as? Int ?? 0
            petName = ((t["pet"] as? [String: Any])?["name"] as? String) ?? ""
        }
        messages = present(r.json["messages"] as? [[String: Any]] ?? [])
    }

    private func refresh() async {
        guard threadId > 0, !sending else { return }
        let after = messages.last?.id ?? 0
        guard let r = try? await api.request("api/si/messages.php?thread_id=\(threadId)&after_id=\(after)"), r.json["ok"] as? Bool == true else { return }
        let fresh = present(r.json["messages"] as? [[String: Any]] ?? [])
        if !fresh.isEmpty { messages += fresh.filter { n in !messages.contains(where: { $0.id == n.id }) } }
    }

    private func send(_ raw: String) async {
        let body = raw.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !body.isEmpty, threadId > 0 else { return }
        sending = true; note = ""; text = ""
        defer { sending = false }
        messages.append(ChatMessage(id: -Int(Date().timeIntervalSince1970), fromMe: true, body: body, choices: []))
        do {
            let r = try await api.request("api/si/send.php", method: "POST", body: ["thread_id": threadId, "body": body])
            if r.json["ok"] as? Bool != true {
                note = r.json["error"] as? String ?? "SIPO could not answer. Please try again."
            }
            await load()
        } catch { note = "No connection. Please try again." }
    }

    private func upload(_ item: PhotosPickerItem) async {
        defer { picked = nil }
        guard threadId > 0, let data = try? await item.loadTransferable(type: Data.self) else { return }
        sending = true; note = ""
        defer { sending = false }
        let jpeg = UIImage(data: data)?.jpegData(compressionQuality: 0.85) ?? data   // phone photos can be HEIC
        let r = (try? await api.upload(data: jpeg, filename: "photo.jpg", mime: "image/jpeg", threadId: threadId)) ?? [:]
        if r["ok"] as? Bool != true { note = r["error"] as? String ?? "Could not read that photo." }
        await load()
    }
}

/// Answer buttons SIPO offers instead of typing.
struct FlowButtons: View {
    let items: [String]
    let tap: (String) -> Void
    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            ForEach(items, id: \.self) { c in
                Button(c) { tap(c) }
                    .font(.subheadline.weight(.semibold))
                    .padding(.horizontal, 12).padding(.vertical, 8)
                    .overlay(Rectangle().stroke(Color.accentColor))
            }
        }
    }
}
