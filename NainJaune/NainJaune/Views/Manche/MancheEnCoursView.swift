import SwiftUI

/// Ecran pendant la manche : cases interactives + liste joueurs + bouton fin de manche
struct MancheEnCoursView: View {
    @Environment(GestionnaireDeJeu.self) private var jeu
    @State private var carteSelectionnee: CarteSpeciale? = nil

    var body: some View {
        VStack(spacing: 0) {
            // En-tete
            HStack {
                VStack(alignment: .leading) {
                    Text("Manche \(jeu.mancheNumero)")
                        .font(.system(.title2, design: .serif, weight: .bold))
                        .foregroundStyle(.orJeton)

                    if let donneur = jeu.donneur {
                        Text("Donneur : \(donneur.prenom)")
                            .font(.system(.caption, design: .rounded))
                            .foregroundStyle(.cremeCarte.opacity(0.7))
                    }
                }

                Spacer()

                // Compteur de cartes jouees
                let jouees = jeu.cartesJoueesCetteManche.count
                Text("\(jouees)/5")
                    .font(.system(.headline, design: .monospaced, weight: .bold))
                    .foregroundStyle(.cremeCarte.opacity(0.6))
            }
            .padding(.horizontal)
            .padding(.top, DesignTokens.espacement)
            .padding(.bottom, 8)

            ScrollView {
                VStack(spacing: DesignTokens.espacement) {
                    // Cases interactives
                    casesInteractives

                    // Liste des joueurs
                    ListeJoueursView()
                        .padding(.horizontal)

                    Spacer(minLength: 80)
                }
            }

            // Bouton Fin de manche (fixe en bas)
            VStack {
                Button {
                    HaptiqueManager.impactMoyen()
                    jeu.demanderFinDeManche()
                } label: {
                    HStack(spacing: 8) {
                        Image(systemName: "flag.checkered")
                        Text("Fin de manche")
                    }
                }
                .buttonStyle(BoutonTapisStyle(couleurFond: .bordeaux))
                .padding(.horizontal, 40)
            }
            .padding(.vertical, DesignTokens.espacement)
            .background(
                Color.fondApp
                    .shadow(color: .black.opacity(0.3), radius: 8, y: -4)
            )
        }
        .sheet(item: $carteSelectionnee) { carte in
            SelectionJoueurSheet(carte: carte) { joueurId in
                jeu.declarerCarteJouee(carte: carte, parJoueur: joueurId)
            }
            .presentationDetents([.medium])
        }
    }

    // MARK: - Cases interactives

    @ViewBuilder
    private var casesInteractives: some View {
        VStack(spacing: 8) {
            // Premiere rangee : 3 cases
            HStack(spacing: 8) {
                ForEach(jeu.cases.prefix(3)) { casePlateau in
                    boutonCase(casePlateau)
                }
            }

            // Deuxieme rangee : 2 cases
            HStack(spacing: 8) {
                ForEach(jeu.cases.suffix(2)) { casePlateau in
                    boutonCase(casePlateau)
                }
            }
            .padding(.horizontal, 40)
        }
        .padding(.horizontal)
    }

    private func boutonCase(_ casePlateau: CasePlateau) -> some View {
        let estJouee = jeu.cartesJoueesCetteManche[casePlateau.carte] != nil
        let joueurNom: String? = {
            guard let joueurId = jeu.cartesJoueesCetteManche[casePlateau.carte] else { return nil }
            return jeu.joueurs.first { $0.id == joueurId }?.prenom
        }()

        return BoutonCarteSpecialeView(
            casePlateau: casePlateau,
            estJouee: estJouee,
            joueurQuiAJoue: joueurNom,
            onTap: {
                carteSelectionnee = casePlateau.carte
            }
        )
    }
}
