"""
Vues pour l'authentification
- Inscription enseignant
- Inscription administrateur
- Connexion / Déconnexion
- Gestion OTP
"""

from rest_framework.decorators import api_view, permission_classes
from rest_framework.response import Response
from rest_framework.permissions import IsAuthenticated, AllowAny
from rest_framework import status
from django.contrib.auth import authenticate
from rest_framework_simplejwt.tokens import RefreshToken
from django.utils import timezone
from datetime import timedelta
import random
from ..models import OTPVerification
from ..models import CustomUser, Enseignant, Administrateur
from ..serializers import (
    RegisterEnseignantSerializer,
    RegisterAdministrateurSerializer,
    OTPRequestSerializer,
    OTPVerifySerializer
)


@api_view(['POST'])
@permission_classes([AllowAny])
def register_enseignant(request):
    """Inscription d'un nouvel enseignant"""
    print("=" * 60)
    print("INSCRIPTION ENSEIGNANT")
    print("Données reçues:", request.data)
    print("=" * 60)

    serializer = RegisterEnseignantSerializer(data=request.data)

    print("\n Validation du serializer...")

    if serializer.is_valid():
        print("✅ Validation réussie!")

        # Créer l'enseignant
        enseignant = serializer.save()
        email = enseignant.user.email

        print(f"✅ Enseignant créé: {enseignant}")

        # 🎯 GÉNÉRER UN CODE OTP AUTOMATIQUEMENT
        print("\n🔐 Génération du code OTP...")
        code = ''.join([str(random.randint(0, 9)) for _ in range(6)])
        expires_at = timezone.now() + timedelta(minutes=10)

        # Supprimer les anciens OTP non utilisés pour cet email
        OTPVerification.objects.filter(email=email, is_used=False).delete()
        print("🗑️ Anciens OTP supprimés")

        # Créer le nouvel OTP
        otp = OTPVerification.objects.create(
            email=email,
            code=code,
            expires_at=expires_at
        )

        print(f"✅ Code OTP généré: {code}")
        print(f"   Expire à: {expires_at}")
        print("=" * 60)

        return Response({
            'message': 'Inscription réussie. Un code OTP a été envoyé à votre email.',
            'email': email,
            'otp_code': code  # ⚠️ À RETIRER EN PRODUCTION !
        }, status=status.HTTP_201_CREATED)
    else:
        print("❌ Validation échouée!")
        print("Erreurs du serializer:", serializer.errors)
        print("=" * 60)

        return Response({
            'message': 'Erreur de validation',
            'details': serializer.errors
        }, status=status.HTTP_400_BAD_REQUEST)

@api_view(['POST'])
@permission_classes([AllowAny])
def register_administrateur(request):
    """Inscription d'un nouvel administrateur"""
    serializer = RegisterAdministrateurSerializer(data=request.data)
    if serializer.is_valid():
        admin = serializer.save()
        return Response({
            'message': 'Inscription réussie. Votre compte est en attente de validation par un super-administrateur.',
            'email': admin.user.email
        }, status=status.HTTP_201_CREATED)
    return Response(serializer.errors, status=status.HTTP_400_BAD_REQUEST)


@api_view(['POST'])
@permission_classes([AllowAny])
def request_otp(request):
    """Demander un code OTP"""
    serializer = OTPRequestSerializer(data=request.data)
    if serializer.is_valid():
        otp = serializer.save()
        return Response({
            'message': 'Code OTP envoyé à votre email.',
            'code': otp.code  # À RETIRER EN PRODUCTION
        }, status=status.HTTP_200_OK)
    return Response(serializer.errors, status=status.HTTP_400_BAD_REQUEST)


@api_view(['POST'])
@permission_classes([AllowAny])
def verify_otp(request):
    """Vérifier le code OTP"""
    serializer = OTPVerifySerializer(data=request.data)
    if serializer.is_valid():
        return Response({
            'message': 'Email vérifié avec succès. Vous pouvez maintenant vous connecter.'
        }, status=status.HTTP_200_OK)
    return Response(serializer.errors, status=status.HTTP_400_BAD_REQUEST)



@api_view(['POST'])
@permission_classes([AllowAny])
def login(request):
    """Connexion d'un utilisateur"""
    print("=" * 60)
    print("TENTATIVE DE CONNEXION")
    print("=" * 60)

    email = request.data.get('email')
    password = request.data.get('password')

    if not email or not password:
        print("❌ Email ou mot de passe manquant")
        print("=" * 60)

        return Response({
            'message': 'Email et mot de passe requis.'
        }, status=status.HTTP_400_BAD_REQUEST)

    print(f"Email: {email}")

    # Authentifier avec l'email comme username
    #user = authenticate(username=email, password=password)
    try:
        user_obj = CustomUser.objects.get(email=email)
        user = authenticate(username=user_obj.username, password=password)
    except CustomUser.DoesNotExist:
        user = None

    if user is None:
        print("❌ Identifiants invalides")
        print("=" * 60)

        return Response({
            'message': 'Identifiants invalides.'
        }, status=status.HTTP_401_UNAUTHORIZED)

    print(f"✅ Utilisateur trouvé: {user.username} ({user.role})")

    # Vérifications spécifiques selon le rôle
    if user.role == 'enseignant':
        try:
            enseignant = user.enseignant_profile

            if not enseignant.is_verified:
                print("⚠️ Email non vérifié")
                print("=" * 60)

                return Response({
                    'message': 'Veuillez vérifier votre email avant de vous connecter.',
                    'email': user.email,
                    'needs_verification': True
                }, status=status.HTTP_403_FORBIDDEN)

            if not enseignant.is_active:
                print("⚠️ Compte non activé")
                print("=" * 60)

                return Response({
                    'error': 'Votre compte n\'est pas encore activé.',
                }, status=status.HTTP_403_FORBIDDEN)

            print(f"✅ Enseignant vérifié et actif: {enseignant}")

        except Enseignant.DoesNotExist:
            print("❌ Profil enseignant non trouvé")
            print("=" * 60)

            return Response({
                'error': 'Profil enseignant non trouvé.'
            }, status=status.HTTP_404_NOT_FOUND)

    elif user.role == 'administrateur':
        try:
            admin = user.admin_profile

            if not admin.is_approved:
                print("⚠️ Compte administrateur non approuvé")
                print("=" * 60)

                return Response({
                    'error': 'Votre compte est en attente de validation par un administrateur.',
                }, status=status.HTTP_403_FORBIDDEN)

            print(f"✅ Administrateur approuvé: {admin}")

        except Administrateur.DoesNotExist:
            print("❌ Profil administrateur non trouvé")
            print("=" * 60)

            return Response({
                'error': 'Profil administrateur non trouvé.'
            }, status=status.HTTP_404_NOT_FOUND)

    # 🎯 GÉNÉRER LES TOKENS JWT
    refresh = RefreshToken.for_user(user)
    access_token = str(refresh.access_token)
    refresh_token = str(refresh)

    print("✅ Tokens JWT générés")
    print(f"Access Token: {access_token[:50]}...")
    print("✅ Connexion réussie")
    print("=" * 60)

    return Response({
        'message': 'Connexion réussie.',
        'access': access_token,
        'refresh': refresh_token,
        'user': {
            'id': user.id,
            'email': user.email,
            'role': user.role,
            'username': user.username
        }
    }, status=status.HTTP_200_OK)

@api_view(['POST'])
@permission_classes([IsAuthenticated])
def logout(request):
    """Déconnexion"""
    return Response({
        'message': 'Déconnexion réussie.'
    }, status=status.HTTP_200_OK)