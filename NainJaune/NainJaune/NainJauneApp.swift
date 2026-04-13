import SwiftUI

/// Point d'entree de l'application Nain Jaune
@main
struct NainJauneApp: App {
    @State private var jeu = GestionnaireDeJeu()

    var body: some Scene {
        WindowGroup {
            ContentView()
                .environment(jeu)
        }
    }
}
