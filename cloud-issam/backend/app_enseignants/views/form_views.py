"""
Vues pour le formulaire d'inscription
- Récupération des données de référence (niveaux, filières, spécialités)
- Filtrage des matières
"""

from rest_framework.decorators import api_view, permission_classes
from rest_framework.response import Response
from rest_framework.permissions import AllowAny
from rest_framework import status
from django.db.models import Q
from ..models import Niveau, Filiere, Specialite, Matiere
from ..serializers import NiveauSerializer, FiliereSerializer, MatiereSerializer


@api_view(['GET'])
@permission_classes([AllowAny])
def get_form_data(request):
    """
    Récupère toutes les données pour le formulaire d'inscription
    Retourne: niveaux, filières, spécialités
    """
    try:
        niveaux = Niveau.objects.all().order_by('ordre')
        filieres = Filiere.objects.all().order_by('nom')
        specialites = Specialite.objects.all().select_related('filiere').order_by('filiere__nom', 'nom')

        specialites_data=[]
        for spec in specialites:
            specialites_data.append({
                'id':spec.id,
                'nom':spec.nom,
                'filiere':spec.filiere.id,
                'filiere_nom':spec.filiere.nom,
                'description':spec.description or ''
            })
        return Response({
            'niveaux': NiveauSerializer(niveaux, many=True).data,
            'filieres': FiliereSerializer(filieres, many=True).data,
            # 'specialites': SpecialiteSerializer(specialites, many=True).data
            'specialites': specialites_data
        }, status=status.HTTP_200_OK)
    except Exception as e:
        return Response({
            'error': str(e)
        }, status=status.HTTP_500_INTERNAL_SERVER_ERROR)


@api_view(['POST'])
@permission_classes([AllowAny])
def get_matieres_filtered(request):
    """
    Filtre les matières selon niveau, filière et spécialités
    """
    print("=" * 60)
    print("DONNÉES REÇUES:")
    print(f"request.data = {request.data}")

    niveau_nom = request.data.get('niveau')
    filiere_nom = request.data.get('filiere')
    specialites_noms = request.data.get('specialites', [])

    print(f"niveau_nom = {niveau_nom}")
    print(f"filiere_nom = {filiere_nom}")
    print(f"specialites_noms = {specialites_noms}")
    print(f"Nombre de spécialités = {len(specialites_noms)}")
    print("=" * 60)

    # Validation
    if not niveau_nom or not filiere_nom:
        return Response(
            {'error': 'Niveau et filière sont requis'},
            status=status.HTTP_400_BAD_REQUEST
        )

    try:
        # Recherche du niveau
        print(f"\n🔍 ÉTAPE 1: Recherche du niveau '{niveau_nom}'")
        niveau = Niveau.objects.get(nom=niveau_nom)
        print(f"✅ Niveau trouvé: {niveau}")

        # Recherche de la filière
        print(f"\n🔍 ÉTAPE 2: Recherche de la filière '{filiere_nom}'")
        filiere = Filiere.objects.get(nom=filiere_nom)
        print(f"✅ Filière trouvée: {filiere}")

        # Filtrer par niveau et filière (base)
        print(f"\n🔍 ÉTAPE 3: Filtrage par niveau + filière")
        matieres_query = Matiere.objects.filter(
            niveau_id=niveau.id,
            filiere_id=filiere.id
        )
        print(f"📊 Matières trouvées (avant filtre spécialités): {matieres_query.count()}")

        # Si des spécialités sont fournies, filtrer aussi par spécialités
        if specialites_noms and len(specialites_noms) > 0:
            print(f"\n🔍 ÉTAPE 4: Filtrage par spécialités")
            print(f"Spécialités demandées: {specialites_noms}")

            # Récupérer les IDs des spécialités
            specialites = Specialite.objects.filter(
                nom__in=specialites_noms,
                filiere=filiere
            )

            print(f"✅ {specialites.count()} spécialité(s) trouvée(s) dans la BD")
            for spec in specialites:
                print(f"  - {spec.nom}")

            if specialites.exists():
                # Filtrer les matières qui ont AU MOINS UNE des spécialités sélectionnées
                # OU les matières sans spécialité (matières générales/communes)
                matieres_query = matieres_query.filter(
                    Q(specialites__in=specialites) | Q(specialites__isnull=True)
                ).distinct()

                print(f"📊 Matières après filtre spécialités: {matieres_query.count()}")
            else:
                print("⚠️ Aucune spécialité trouvée correspondant aux noms fournis")
        else:
            print(f"\n⚠️ Aucune spécialité fournie, retour de toutes les matières du niveau/filière")

        # Récupérer les noms des matières
        matieres_list = list(matieres_query.values_list('nom', flat=True))

        print(f"\n✅ RÉSULTAT FINAL: {len(matieres_list)} matière(s)")
        for i, nom in enumerate(matieres_list, 1):
            print(f"  {i}. {nom}")
        print("=" * 60)

        return Response({
            'matieres': matieres_list,
            'count': len(matieres_list)
        }, status=status.HTTP_200_OK)

    except Niveau.DoesNotExist:
        print(f"❌ Niveau '{niveau_nom}' non trouvé")
        return Response(
            {'error': f'Niveau "{niveau_nom}" non trouvé'},
            status=status.HTTP_404_NOT_FOUND
        )
    except Filiere.DoesNotExist:
        print(f"❌ Filière '{filiere_nom}' non trouvée")
        return Response(
            {'error': f'Filière "{filiere_nom}" non trouvée'},
            status=status.HTTP_404_NOT_FOUND
        )
    except Exception as e:
        print(f"\n❌ ERREUR: {str(e)}")
        import traceback
        traceback.print_exc()

        return Response(
            {'error': str(e)},
            status=status.HTTP_500_INTERNAL_SERVER_ERROR
        )