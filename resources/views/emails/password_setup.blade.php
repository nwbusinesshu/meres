<p>Üdv!</p>
<p>A(z) <strong>{{ $org }}</strong> szervezet meghívott a 360 értékelési rendszerbe a <strong>{{ $email }}</strong> e-mail címmel.</p>
<p>Az első belépéshez állíts be jelszót: <a href="{{ $url }}">{{ $url }}</a></p>
<p>Ez a link {{ $expires_at->format('Y-m-d H:i') }} időpontig érvényes.</p>
<p>Ha nem te kezdeményezted, hagyd figyelmen kívül ezt a levelet.</p>
