import SwiftUI

/// Dashboard principal : cases du plateau + liste des joueurs + bouton nouvelle manche
struct PlateauView: View {
    @Environment(GestionnaireDeJeu.self) private var jeu
    @State private var apparition = false

    var body: some View {
        VStack(spacing: DesignTokens.espacement) {
            // En-tete
            HStack {
                Text("Le Nain Jaune")
                    .font(.system(.title2, design: .serif, weight: .bold))
                    .foregroundStyle(.orJeton)

                Spacer()

                // Total des pots
                HStack(spacing: 4) {
                    Image(systemName: "circle.fill")
                        .font(.system(size: 10))
                        .foregroundStyle(.orJeton)
                    Text("\(jeu.totalPots)")
                        .font(.system(.headline, design: .monospaced, weight: .bold))
                        .foregroundStyle(.orJeton)
                        .animerValeur(jeu.totalPots)
                    Text("sur le plateau")
                        .font(.system(.caption, design: .rounded))
                        .foregroundStyle(.cremeCarte.opacity(0.6))
                }
            }
            .padding(.horizontal)
            .padding(.top, DesignTokens.espacement)

            ScrollView {
                VStack(spacing: DesignTokens.espacement) {
                    // Les 5 cases du plateau
                    casesPlateauSection
                        .opacity(apparition ? 1 : 0)
                        .offset(y: apparition ? 0 : 20)

                    // Liste des joueurs
                    ListeJoueursView()
                        .padding(.horizontal)
                        .opacity(apparition ? 1 : 0)
                        .offset(y: apparition ? 0 : 20)

                    Spacer(minLength: 80)
                }
            }

            // Bouton Nouvelle Manche (fixe en bas)
            VStack {
                Button {
                    jeu.demarrerNouvelleManche()
                } label: {
                    HStack(spacing: 8) {
                        Image(systemName: "play.fill")
                        Text(jeu.mancheNumero == 0 ? "Premiere manche" : "Manche suivante")
                    }
                }
                .buttonStyle(BoutonTapisStyle())
                .padding(.horizontal, 40)
            }
            .padding(.vertical, DesignTokens.espacement)
            .background(
                Color.fondApp
                    .shadow(color: .black.opacity(0.3), radius: 8, y: -4)
            )
        }
        .onAppear {
            withAnimation(.easeOut(duration: 0.6)) {
                apparition = true
            }
        }
    }

    // MARK: - Cases du plateau

    @ViewBuilder
    private var casesPlateauSection: some View {
        VStack(spacing: 8) {
            // Premiere rangee : 3 cases
            HStack(spacing: 8) {
                ForEach(jeu.cases.prefix(3)) { casePlateau in
                    CasePlateauView(casePlateau: casePlateau)
                }
            }

            // Deuxieme rangee : 2 cases (centrees)
            HStack(spacing: 8) {
                ForEach(jeu.cases.suffix(2)) { casePlateau in
                    CasePlateauView(casePlateau: casePlateau)
                }
            }
            .padding(.horizontal, 40)
        }
        .padding(.horizontal)
    }
}
