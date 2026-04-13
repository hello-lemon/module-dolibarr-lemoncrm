import SwiftUI

/// Ecran principal de fin de manche : gagnant, points, penalites, recapitulatif
struct FinDeMancheView: View {
    @Environment(GestionnaireDeJeu.self) private var jeu
    @State private var etape: EtapeFinDeManche = .selectionGagnant

    enum EtapeFinDeManche {
        case selectionGagnant
        case saisiePoints
        case penalites
        case recapitulatif
    }

    var body: some View {
        VStack(spacing: 0) {
            // En-tete
            VStack(spacing: 4) {
                Text("Fin de manche \(jeu.mancheNumero)")
                    .font(.system(.title2, design: .serif, weight: .bold))
                    .foregroundStyle(.orJeton)

                // Indicateur d'etape
                HStack(spacing: 6) {
                    ForEach(0..<4) { index in
                        Circle()
                            .fill(indexEtape >= index ? Color.orJeton : Color.cremeCarte.opacity(0.3))
                            .frame(width: 8, height: 8)
                    }
                }
            }
            .padding(.top, DesignTokens.espacement)
            .padding(.bottom, 8)

            // Contenu selon l'etape
            ScrollView {
                VStack(spacing: DesignTokens.espacementLarge) {
                    switch etape {
                    case .selectionGagnant:
                        SelectionGagnantView()
                            .padding(.horizontal)

                        // Bouton Grand Opera
                        boutonGrandOpera
                            .padding(.horizontal)

                    case .saisiePoints:
                        SaisiePointsView()
                            .padding(.horizontal)

                    case .penalites:
                        PenaliteCartesView()
                            .padding(.horizontal)

                    case .recapitulatif:
                        RecapitulatifTransfertView()
                            .padding(.horizontal)
                    }

                    Spacer(minLength: 100)
                }
            }

            // Boutons de navigation en bas
            boutonsNavigation
        }
    }

    // MARK: - Bouton Grand Opera

    @ViewBuilder
    private var boutonGrandOpera: some View {
        if jeu.gagnantId != nil {
            Button {
                jeu.basculerGrandOpera()
                HaptiqueManager.selection()
            } label: {
                HStack(spacing: 8) {
                    Image(systemName: jeu.estGrandOpera ? "checkmark.circle.fill" : "circle")
                        .foregroundStyle(jeu.estGrandOpera ? .orJeton : .cremeCarte.opacity(0.5))

                    VStack(alignment: .leading, spacing: 2) {
                        Text("Grand Opera")
                            .font(.system(.body, design: .rounded, weight: .semibold))
                            .foregroundStyle(.orJeton)
                        Text("Le gagnant a vide sa main des le premier tour")
                            .font(.system(.caption2, design: .rounded))
                            .foregroundStyle(.cremeCarte.opacity(0.6))
                    }

                    Spacer()

                    if jeu.estGrandOpera {
                        Image(systemName: "sparkles")
                            .foregroundStyle(.orJeton)
                    }
                }
                .padding(DesignTokens.espacement)
                .background(
                    RoundedRectangle(cornerRadius: 12)
                        .fill(jeu.estGrandOpera ? Color.orJeton.opacity(0.15) : Color.vertTapis.opacity(0.3))
                        .overlay(
                            RoundedRectangle(cornerRadius: 12)
                                .stroke(jeu.estGrandOpera ? Color.orJeton.opacity(0.5) : Color.clear, lineWidth: 1)
                        )
                )
            }
        }
    }

    // MARK: - Boutons de navigation

    @ViewBuilder
    private var boutonsNavigation: some View {
        HStack(spacing: DesignTokens.espacement) {
            // Bouton Precedent
            if etape != .selectionGagnant {
                Button {
                    withAnimation(.easeInOut(duration: 0.3)) {
                        etapePrecedente()
                    }
                } label: {
                    HStack(spacing: 4) {
                        Image(systemName: "chevron.left")
                        Text("Retour")
                    }
                }
                .buttonStyle(BoutonSecondaireStyle())
            }

            // Bouton Suivant / Valider
            if etape == .recapitulatif {
                Button {
                    jeu.validerFinDeManche()
                } label: {
                    HStack(spacing: 4) {
                        Image(systemName: "checkmark")
                        Text("Valider")
                    }
                }
                .buttonStyle(BoutonTapisStyle())
            } else {
                Button {
                    withAnimation(.easeInOut(duration: 0.3)) {
                        etapeSuivante()
                    }
                } label: {
                    HStack(spacing: 4) {
                        Text("Suivant")
                        Image(systemName: "chevron.right")
                    }
                }
                .buttonStyle(BoutonTapisStyle(estDesactive: !peutAvancer))
                .disabled(!peutAvancer)
            }
        }
        .padding(.horizontal, DesignTokens.espacement)
        .padding(.vertical, DesignTokens.espacement)
        .background(
            Color.fondApp
                .shadow(color: .black.opacity(0.3), radius: 8, y: -4)
        )
    }

    // MARK: - Navigation entre etapes

    private var indexEtape: Int {
        switch etape {
        case .selectionGagnant: 0
        case .saisiePoints: 1
        case .penalites: 2
        case .recapitulatif: 3
        }
    }

    private var peutAvancer: Bool {
        switch etape {
        case .selectionGagnant:
            return jeu.gagnantId != nil
        case .saisiePoints:
            return true
        case .penalites:
            return true
        case .recapitulatif:
            return true
        }
    }

    private func etapeSuivante() {
        switch etape {
        case .selectionGagnant:
            if jeu.estGrandOpera {
                etape = .recapitulatif
            } else {
                etape = .saisiePoints
            }
        case .saisiePoints:
            if jeu.cartesNonJoueesCetteManche.isEmpty {
                etape = .recapitulatif
            } else {
                etape = .penalites
            }
        case .penalites:
            etape = .recapitulatif
        case .recapitulatif:
            break
        }
    }

    private func etapePrecedente() {
        switch etape {
        case .selectionGagnant:
            break
        case .saisiePoints:
            etape = .selectionGagnant
        case .penalites:
            etape = .saisiePoints
        case .recapitulatif:
            if jeu.estGrandOpera {
                etape = .selectionGagnant
            } else if jeu.cartesNonJoueesCetteManche.isEmpty {
                etape = .saisiePoints
            } else {
                etape = .penalites
            }
        }
    }
}
