<?php

namespace Marcoboom\MediaManager\Models;

use Illuminate\Database\Eloquent\Model;
use Storage;

use Marcoboom\MediaManager\Imagestyle;

class Media extends Model
{
	/**
	 * Database table to use
	 * @var string
	 */
    protected $table = 'media';

	/**
	 * Attributes of the model which can be mass assigned
	 * @var array
	 */
	protected $fillable = ['active','user_id', 'name','size','type','storage','source','styles'];

	/**
	 * Atributes to cast in the model as the given value
	 * @var array
	 */
	protected $casts = ['active'=>'boolean', 'styles'=>'array'];

	/**
	 * Accessors to add to a model when exporting it to JSON
	 * @var array
	 */
	protected $appends = ['url','thumbnail','sizeFormatted'];

	/**
	 * Define the relationship with a user model
	 * @return Relation
	 */
	public function user()
	{
		return $this->belongsTo(config('auth.providers.users.model'));
	}

	/**
	 * Shortcut to get direct access to the url of the thumbnail
	 * @return string
	 */
	public function getThumbnailAttribute()
	{
		return $this->imagestyleUrl('thumbnail');
	}

	/**
	 * Accessor wich gives directly the url of the stored media item
	 * @return string
	 */
	public function getUrlAttribute()
	{
		if ($this->storage !== 'local')
		{
			return Storage::disk($this->storage)->url($this->source);
		}

		return url(implode('/', [config('media.controllers.file'), $this->id, $this->name]));
	}

	/**
	 * Create a boolean to check if a file is an image
	 *
	 * @access public
	 * @return boolean
	 */
	public function getImageAttribute()
	{
		return str_contains($this->type, 'image/');
	}

	/**
	 * Select only images
	 *
	 * @access public
	 * @param mixed $query
	 * @return void
	 */
	public function scopeImages($query)
	{
		$query->where('type', 'LIKE', 'image/%');
	}

	/**
	 * Get the size of a file in a readable format
	 *
	 * @access public
	 * @return string
	 */
	public function getSizeFormattedAttribute()
	{
		$precision = 2;
		$base = log($this->size, 1024);
		$suffixes = array('', 'k', 'M', 'G', 'T');

		return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
	}

	/**
	 * Get information about all available imagestyles, including a url and path
	 * @return Collection
	 */
	public function imagestyles()
	{
		$return = collect();

		foreach (config('media.imagestyles') as $key=>$value)
		{
			$return->push([
				'path'		=>	$this->imagestylePath($key),
				'url'		=>	$this->imagestyleUrl($key),
			]);
		}

		return $return;
	}

	/**
	 * Get the full url of an imagestyle
	 *
	 * @param mixed $crop
	 * @return string
	 */
	public function imagestyleUrl($style)
	{
		if (!$path = $this->imagestylePath($style))
		{
			return url(implode('/', ['img',$style, $this->id, $this->name]));
		}

		return Storage::disk($this->storage)->url($path);
	}

	/**
	 * Get the path of an imagestyle
	 * @param  string $style
	 * @return string | null
	 */
	public function imagestylePath($style)
	{
		if (!$this->imagestyleExists($style) || $this->storage === 'local') return null;

		$imagestyle = new Imagestyle($this, $style);
		return $imagestyle->getPath();
	}

	/**
	 * Check if there is a stored imagestyle available
	 * @param  string $style
	 * @return boolean
	 */
	public function imagestyleExists($style)
	{
		if (!isset($this->styles[$style])) return false;

		return $this->styles[$style] >= $this->updated_at->timestamp;
	}

	/**
	 * Extend the default delete function to also delete the related storage item
	 * @return boolean
	 */
	public function delete()
	{
	   $this->deleteInStorage();

	   return parent::delete();
	}

	/**
	 * Delete the storaged item including the stored imagestyles
	 * @return void
	 */
	public function deleteInStorage()
	{
	   $driver = Storage::disk($this->storage);
	   $driver->delete($this->source);

	   $styles = $this->imagestyles()->filter(function($item)
	   {
	 		return $item['path'];
	   });
	   foreach ($styles as $style)
	   {
		   $driver->delete($style['path']);
	   }
	}

	/**
	 * Delete multiple files at once
	 *
	 * @access public
	 * @static
	 * @param array $ids
	 * @return int | null
	 */

	public static function deleteMultiple(array $collection)
	{
		if (!is_array($collection)) return null;

		$trigger = 0;

		foreach ($collection as $obj)
		{
			if (!$obj instanceof self)
			{
				$obj = static::find($obj);
			}

			if ($obj instanceof self)
			{
				$obj->delete();
				$trigger++;
			}
		}

		return $trigger ? $trigger : null;
	}

}