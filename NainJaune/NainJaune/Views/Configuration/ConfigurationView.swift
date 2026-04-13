import SwiftUI

/// Ecran de configuration : saisie des prenoms et du solde initial
struct ConfigurationView: View {
    @Environment(GestionnaireDeJeu.self) private var jeu

    var body: some View {
        @Bindable var jeu = jeu

        VStack(spacing: 0) {
            // En-tete
            VStack(spacing: 8) {
                Text("Configuration")
                    .font(.system(.title, design: .serif, weight: .bold))
                    .foregroundStyle(.orJeton)

                Text("Manche \(jeu.mancheNumero == 0 ? "initiale" : "\(jeu.mancheNumero)")")
                    .font(.system(.subheadline, design: .rounded))
                    .foregroundStyle(.cremeCarte.opacity(0.7))
            }
            .padding(.top, DesignTokens.espacementLarge)
            .padding(.bottom, DesignTokens.espacement)

            ScrollView {
                VStack(spacing: DesignTokens.espacement) {

                    // Section joueurs
                    VStack(alignment: .leading, spacing: DesignTokens.espacement) {
                        HStack {
                            Text("Joueurs (\(jeu.joueurs.count))")
                                .font(.system(.headline, design: .rounded, weight: .semibold))
                                .foregroundStyle(.cremeCarte)

                            Spacer()

                            if jeu.joueurs.count < 8 {
                                Button {
                                    withAnimation(.spring(response: 0.3)) {
                                        jeu.ajouterJoueur()
                                    }
                                    HaptiqueManager.selection()
                                } label: {
                                    Image(systemName: "plus.circle.fill")
                                        .font(.title2)
                                        .foregroundStyle(.orJeton)
                                }
                            }
                        }

                        ForEach(Array(jeu.joueurs.enumerated()), id: \.element.id) { index, joueur in
                            LigneJoueurView(
                                index: index,
                                joueur: $jeu.joueurs[index],
                                peutSupprimer: jeu.joueurs.count > 3,
                                onSupprimer: {
                                    withAnimation(.spring(response: 0.3)) {
                                        jeu.supprimerJoueur(id: joueur.id)
                                    }
                                    HaptiqueManager.selection()
                                }
                            )
                        }
                    }
                    .padding(DesignTokens.espacement)
                    .background(
                        RoundedRectangle(cornerRadius: DesignTokens.rayonCarte)
                            .fill(Color.vertTapis.opacity(0.5))
                    )

                    // Section solde initial
                    VStack(alignment: .leading, spacing: DesignTokens.espacement) {
                        Text("Solde initial")
                            .font(.system(.headline, design: .rounded, weight: .semibold))
                            .foregroundStyle(.cremeCarte)

                        HStack {
                            Button {
                                if jeu.soldeInitial > 20 {
                                    jeu.soldeInitial -= 10
                                    HaptiqueManager.selection()
                                }
                            } label: {
                                Image(systemName: "minus.circle.fill")
                                    .font(.title2)
                                    .foregroundStyle(jeu.soldeInitial > 20 ? .orJeton : .gray)
                            }
                            .disabled(jeu.soldeInitial <= 20)

                            Spacer()

                            HStack(spacing: 4) {
                                Text("\(jeu.soldeInitial)")
                                    .font(.system(.largeTitle, design: .monospaced, weight: .bold))
                                    .foregroundStyle(.orJeton)
                                    .animerValeur(jeu.soldeInitial)

                                Text("jetons")
                                    .font(.system(.body, design: .rounded))
                                    .foregroundStyle(.cremeCarte.opacity(0.7))
                            }

                            Spacer()

                            Button {
                                if jeu.soldeInitial < 200 {
                                    jeu.soldeInitial += 10
                                    HaptiqueManager.selection()
                                }
                            } label: {
                                Image(systemName: "plus.circle.fill")
                                    .font(.title2)
                                    .foregroundStyle(jeu.soldeInitial < 200 ? .orJeton : .gray)
                            }
                            .disabled(jeu.soldeInitial >= 200)
                        }
                        .padding(.horizontal)
                    }
                    .padding(DesignTokens.espacement)
                    .background(
                        RoundedRectangle(cornerRadius: DesignTokens.rayonCarte)
                            .fill(Color.vertTapis.opacity(0.5))
                    )
                }
                .padding(.horizontal)
                .padding(.bottom, 100)
            }

            // Bouton Commencer (fixe en bas)
            VStack {
                Button {
                    jeu.demarrerPartie()
                    HaptiqueManager.impactMoyen()
                } label: {
                    Text("Commencer la partie")
                }
                .buttonStyle(BoutonTapisStyle(estDesactive: !jeu.peutDemarrer))
                .disabled(!jeu.peutDemarrer)
                .padding(.horizontal, 40)
            }
            .padding(.vertical, DesignTokens.espacement)
            .background(
                Color.fondApp
                    .shadow(color: .black.opacity(0.3), radius: 8, y: -4)
            )
        }
    }
}
