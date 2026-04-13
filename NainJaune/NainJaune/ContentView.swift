import SwiftUI

/// Vue racine : aiguille vers le bon ecran selon l'etat de la partie
struct ContentView: View {
    @Environment(GestionnaireDeJeu.self) private var jeu

    var body: some View {
        ZStack {
            // Fond vert tapis sur toute l'app
            Color.fondApp
                .ignoresSafeArea()

            Group {
                switch jeu.etat {
                case .accueil:
                    AccueilView()
                        .transition(.opacity.combined(with: .scale(scale: 0.95)))

                case .configuration:
                    ConfigurationView()
                        .transition(.asymmetric(
                            insertion: .move(edge: .trailing),
                            removal: .move(edge: .leading)
                        ))

                case .attenteManche:
                    PlateauView()
                        .transition(.opacity.combined(with: .scale(scale: 0.98)))

                case .mancheEnCours:
                    MancheEnCoursView()
                        .transition(.opacity)

                case .finDeManche:
                    FinDeMancheView()
                        .transition(.move(edge: .bottom))

                case .partieTerminee:
                    FinDePartieView()
                        .transition(.opacity.combined(with: .scale(scale: 0.9)))
                }
            }
            .animation(.easeInOut(duration: 0.4), value: jeu.etat.description)
        }
        .preferredColorScheme(nil) // Supporte light et dark mode
    }
}

// MARK: - EtatPartie description pour animation

extension EtatPartie: CustomStringConvertible {
    var description: String {
        switch self {
        case .accueil: "accueil"
        case .configuration: "configuration"
        case .attenteManche: "attenteManche"
        case .mancheEnCours: "mancheEnCours"
        case .finDeManche: "finDeManche"
        case .partieTerminee: "partieTerminee"
        }
    }
}
