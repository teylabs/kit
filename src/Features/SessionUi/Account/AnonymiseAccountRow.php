<?php

namespace Tey\Kit\Features\SessionUi\Account;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Features\SessionUi\Contracts\DeletesAccountRow;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;

/**
 * The kit's anonymiser for a deleted account (`kit.lifecycle.account_user_row` = `anonymise`): the user row stays, as a "Deleted user" tombstone, so
 * history that points at it (RESTRICT foreign keys) survives, and everything personal on it is erased for good. Name and email are replaced, the
 * password is a random hash nobody holds, the email is unverified, two-factor secrets, the remember-me token, passkeys, database sessions and the
 * person's files (their avatar and any media on the user) are deleted. The kit keeps the Concierge account deactivated, so the row never signs in.
 *
 * Runs inside the deletion's transaction. Extend it and override attributes() to scrub the app's own personal columns on users as well.
 */
class AnonymiseAccountRow implements DeletesAccountRow
{
    public const NAME = 'Deleted user';

    public function delete(Model $user): void
    {
        $id = (int) $user->getKey();
        foreach ([(new UserAvatar)->getMorphClass(), $user->getMorphClass()] as $type) {
            // Media::delete() removes the file with the row.
            Media::query()->where('model_type', $type)->where('model_id', (string) $id)->get()->each->delete();
        }
        if (method_exists($user, 'passkeys')) {
            $user->passkeys()->delete();
        }
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $id)->delete();
        }
        $columns = array_flip(Schema::getColumnListing($user->getTable()));
        $user->forceFill(array_intersect_key($this->attributes($user), $columns))->saveQuietly();
    }

    /**
     * The values written over the row; columns the table lacks are skipped. The email is unique per row and can never receive mail (.invalid).
     *
     * @return array<string, mixed>
     */
    protected function attributes(Model $user): array
    {
        return [
            'name' => self::NAME,
            'email' => 'deleted-'.$user->getKey().'@deleted.invalid',
            'password' => Hash::make(Str::random(64)),
            'email_verified_at' => null,
            'remember_token' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }
}
