import SwiftUI

/// Ecran d'accueil avec le titre du jeu et le bouton Nouvelle Partie
struct AccueilView: View {
    @Environment(GestionnaireDeJeu.self) private var jeu
    @State private var apparition = false

    var body: some View {
        VStack(spacing: DesignTokens.espacementLarge) {
            Spacer()

            // Symboles decoratifs
            HStack(spacing: 30) {
                Text("\u{2663}")
                    .foregroundStyle(.noirCarte)
                Text("\u{2665}")
                    .foregroundStyle(.rougeCarte)
                Text("\u{2666}")
                    .foregroundStyle(.rougeCarte)
                Text("\u{2660}")
                    .foregroundStyle(.noirCarte)
            }
            .font(.system(size: 36))
            .opacity(apparition ? 1 : 0)
            .offset(y: apparition ? 0 : -20)

            // Titre principal
            VStack(spacing: 8) {
                Text("Le")
                    .font(.system(size: 24, weight: .medium, design: .serif))
                    .foregroundStyle(.cremeCarte.opacity(0.8))

                Text("Nain Jaune")
                    .font(.system(size: 48, weight: .bold, design: .serif))
                    .foregroundStyle(.orJeton)
                    .shadow(color: .orJeton.opacity(0.3), radius: 8, y: 2)
            }
            .opacity(apparition ? 1 : 0)
            .scaleEffect(apparition ? 1.0 : 0.8)

            // Carte du 7 de carreau (le Nain Jaune)
            VStack(spacing: 4) {
                Text("7")
                    .font(.system(size: 60, weight: .bold, design: .serif))
                Text("\u{2666}")
                    .font(.system(size: 40))
            }
            .foregroundStyle(.rougeCarte)
            .frame(width: 100, height: 140)
            .background(
                RoundedRectangle(cornerRadius: DesignTokens.rayonCarte)
                    .fill(Color.cremeCarte)
                    .shadow(color: .black.opacity(0.3), radius: 8, y: 4)
            )
            .rotationEffect(.degrees(apparition ? 0 : -5))
            .opacity(apparition ? 1 : 0)

            Spacer()

            // Bouton Nouvelle Partie
            Button {
                HaptiqueManager.impactMoyen()
                // Preparer 4 joueurs par defaut
                for _ in 0..<4 {
                    jeu.ajouterJoueur()
                }
                withAnimation(.easeInOut(duration: 0.4)) {
                    jeu.etat = .configuration
                }
            } label: {
                Text("Nouvelle Partie")
            }
            .buttonStyle(BoutonTapisStyle())
            .padding(.horizontal, 40)
            .opacity(apparition ? 1 : 0)
            .offset(y: apparition ? 0 : 30)

            Spacer()
                .frame(height: 60)
        }
        .padding()
        .onAppear {
            withAnimation(.easeOut(duration: 0.8)) {
                apparition = true
            }
        }
    }
}
