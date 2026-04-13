import SwiftUI

/// Mini-calculatrice pour saisir les points restants en main
/// Nombre de figures x 10 + total des petites cartes
struct MiniCalculatriceView: View {
    let joueur: Joueur
    let onPointsChanged: (Int) -> Void

    @State private var nombreFigures: Int = 0
    @State private var sommePetitesCartes: Int = 0
    @State private var saisieDirecte: String = ""
    @State private var modeDirecte: Bool = false

    private var total: Int {
        if modeDirecte {
            return Int(saisieDirecte) ?? 0
        }
        return (nombreFigures * 10) + sommePetitesCartes
    }

    var body: some View {
        VStack(spacing: 8) {
            // Toggle mode de saisie
            HStack {
                Text(joueur.prenom)
                    .font(.system(.body, design: .rounded, weight: .semibold))
                    .foregroundStyle(.cremeCarte)

                Spacer()

                Button {
                    modeDirecte.toggle()
                    mettreAJourPoints()
                } label: {
                    Text(modeDirecte ? "Calculatrice" : "Saisie directe")
                        .font(.system(.caption, design: .rounded))
                        .foregroundStyle(.orJeton)
                }
            }

            if modeDirecte {
                // Saisie directe du total
                HStack {
                    Text("Total :")
                        .font(.system(.body, design: .rounded))
                        .foregroundStyle(.cremeCarte.opacity(0.8))

                    TextField("0", text: $saisieDirecte)
                        .keyboardType(.numberPad)
                        .font(.system(.title3, design: .monospaced, weight: .bold))
                        .multilineTextAlignment(.center)
                        .foregroundStyle(.orJeton)
                        .padding(.horizontal, 12)
                        .padding(.vertical, 6)
                        .background(
                            RoundedRectangle(cornerRadius: 8)
                                .fill(Color.cremeCarte)
                        )
                        .frame(width: 100)
                        .onChange(of: saisieDirecte) {
                            mettreAJourPoints()
                        }
                }
            } else {
                // Mode calculatrice : figures + petites cartes
                HStack(spacing: DesignTokens.espacement) {
                    // Stepper figures
                    VStack(spacing: 4) {
                        Text("Figures (V/D/R)")
                            .font(.system(size: 11, design: .rounded))
                            .foregroundStyle(.cremeCarte.opacity(0.7))

                        HStack(spacing: 8) {
                            Button {
                                if nombreFigures > 0 {
                                    nombreFigures -= 1
                                    mettreAJourPoints()
                                }
                            } label: {
                                Image(systemName: "minus.circle.fill")
                                    .font(.title3)
                                    .foregroundStyle(nombreFigures > 0 ? .orJeton : .gray)
                            }
                            .disabled(nombreFigures <= 0)

                            Text("\(nombreFigures)")
                                .font(.system(.title3, design: .monospaced, weight: .bold))
                                .foregroundStyle(.cremeCarte)
                                .frame(width: 30)

                            Button {
                                if nombreFigures < 12 {
                                    nombreFigures += 1
                                    mettreAJourPoints()
                                }
                            } label: {
                                Image(systemName: "plus.circle.fill")
                                    .font(.title3)
                                    .foregroundStyle(nombreFigures < 12 ? .orJeton : .gray)
                            }
                            .disabled(nombreFigures >= 12)
                        }

                        Text("= \(nombreFigures * 10) pts")
                            .font(.system(size: 11, design: .monospaced))
                            .foregroundStyle(.orJeton.opacity(0.7))
                    }

                    Divider()
                        .frame(height: 50)
                        .background(Color.cremeCarte.opacity(0.3))

                    // Petites cartes
                    VStack(spacing: 4) {
                        Text("Petites cartes")
                            .font(.system(size: 11, design: .rounded))
                            .foregroundStyle(.cremeCarte.opacity(0.7))

                        HStack(spacing: 8) {
                            Button {
                                if sommePetitesCartes > 0 {
                                    sommePetitesCartes -= 1
                                    mettreAJourPoints()
                                }
                            } label: {
                                Image(systemName: "minus.circle.fill")
                                    .font(.title3)
                                    .foregroundStyle(sommePetitesCartes > 0 ? .orJeton : .gray)
                            }
                            .disabled(sommePetitesCartes <= 0)

                            Text("\(sommePetitesCartes)")
                                .font(.system(.title3, design: .monospaced, weight: .bold))
                                .foregroundStyle(.cremeCarte)
                                .frame(width: 40)

                            Button {
                                sommePetitesCartes += 1
                                mettreAJourPoints()
                            } label: {
                                Image(systemName: "plus.circle.fill")
                                    .font(.title3)
                                    .foregroundStyle(.orJeton)
                            }
                        }

                        Text("= \(sommePetitesCartes) pts")
                            .font(.system(size: 11, design: .monospaced))
                            .foregroundStyle(.orJeton.opacity(0.7))
                    }
                }
            }

            // Total
            HStack {
                Spacer()
                Text("Total : \(total) jetons")
                    .font(.system(.headline, design: .monospaced, weight: .bold))
                    .foregroundStyle(.orJeton)
            }
        }
        .padding(DesignTokens.espacement)
        .background(
            RoundedRectangle(cornerRadius: DesignTokens.rayonCarte)
                .fill(Color.vertTapis.opacity(0.4))
        )
    }

    private func mettreAJourPoints() {
        onPointsChanged(total)
    }
}
