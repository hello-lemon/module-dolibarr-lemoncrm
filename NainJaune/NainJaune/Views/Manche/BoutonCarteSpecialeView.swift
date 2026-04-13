import SwiftUI

/// Bouton interactif pour une carte speciale pendant la manche
/// Tappable pour declarer quel joueur l'a jouee
struct BoutonCarteSpecialeView: View {
    let casePlateau: CasePlateau
    let estJouee: Bool
    let joueurQuiAJoue: String?
    let onTap: () -> Void

    var body: some View {
        Button(action: onTap) {
            VStack(spacing: 6) {
                // Figure et symbole
                HStack(spacing: 2) {
                    Text(casePlateau.carte.figure)
                        .font(.system(size: 22, weight: .bold, design: .serif))
                    Text(casePlateau.carte.symbole)
                        .font(.system(size: 16))
                }
                .foregroundStyle(estJouee ? .gray : casePlateau.carte.couleurCarte)

                // Pot ou "Gagne par..."
                if estJouee {
                    if let joueur = joueurQuiAJoue {
                        Text(joueur)
                            .font(.system(size: 11, weight: .medium, design: .rounded))
                            .foregroundStyle(.vertReussite)
                            .lineLimit(1)
                            .minimumScaleFactor(0.7)
                    }
                    Image(systemName: "checkmark.circle.fill")
                        .font(.caption)
                        .foregroundStyle(.vertReussite)
                } else {
                    HStack(spacing: 3) {
                        Image(systemName: "circle.fill")
                            .font(.system(size: 8))
                            .foregroundStyle(.orJeton)
                        Text("\(casePlateau.pot)")
                            .font(.system(size: 20, weight: .bold, design: .monospaced))
                            .foregroundStyle(.orJeton)
                            .animerValeur(casePlateau.pot)
                    }
                }
            }
            .padding(10)
            .frame(maxWidth: .infinity)
            .frame(minHeight: 80)
        }
        .buttonStyle(BoutonCarteStyle(estJouee: estJouee))
        .disabled(estJouee)
    }
}
