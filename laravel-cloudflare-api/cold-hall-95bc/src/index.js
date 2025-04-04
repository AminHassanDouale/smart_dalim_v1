export default {
	async fetch(request, env, ctx) {
	  const url = new URL(request.url);
	  const path = url.pathname;

	  // Handle API routes
	  if (path.startsWith('/api/')) {
		return handleApiRequest(request, path, env);
	  }

	  // Default route
	  return new Response("Hello, World!", {
		headers: { 'Content-Type': 'text/plain' },
	  });
	}
  };

  async function handleApiRequest(request, path, env) {
	// Handle users endpoint
	if (path === '/api/users') {
	  if (request.method === 'GET') {
		try {
		  // Query the D1 database for users
		  const { results } = await env.DB.prepare(
			"SELECT * FROM users"
		  ).all();

		  return new Response(JSON.stringify({
			success: true,
			data: results
		  }), {
			headers: { 'Content-Type': 'application/json' }
		  });
		} catch (error) {
		  return new Response(JSON.stringify({
			success: false,
			error: error.message
		  }), {
			status: 500,
			headers: { 'Content-Type': 'application/json' }
		  });
		}
	  } else if (request.method === 'POST') {
		try {
		  const data = await request.json();
		  // Validate required fields
		  if (!data.name || !data.email) {
			return new Response(JSON.stringify({
			  success: false,
			  error: 'Name and email are required'
			}), {
			  status: 400,
			  headers: { 'Content-Type': 'application/json' }
			});
		  }

		  // Insert user into database
		  const result = await env.DB.prepare(
			"INSERT INTO users (name, email) VALUES (?, ?)"
		  ).bind(data.name, data.email).run();

		  return new Response(JSON.stringify({
			success: true,
			id: result.meta.last_row_id
		  }), {
			status: 201,
			headers: { 'Content-Type': 'application/json' }
		  });
		} catch (error) {
		  return new Response(JSON.stringify({
			success: false,
			error: error.message
		  }), {
			status: 500,
			headers: { 'Content-Type': 'application/json' }
		  });
		}
	  }
	}

	// Handle specific user endpoints
	const userIdMatch = path.match(/^\/api\/users\/(\d+)$/);
	if (userIdMatch) {
	  const userId = userIdMatch[1];

	  if (request.method === 'GET') {
		try {
		  // Get user by ID
		  const { results } = await env.DB.prepare(
			"SELECT * FROM users WHERE id = ?"
		  ).bind(userId).all();

		  if (results.length === 0) {
			return new Response(JSON.stringify({
			  success: false,
			  error: 'User not found'
			}), {
			  status: 404,
			  headers: { 'Content-Type': 'application/json' }
			});
		  }

		  return new Response(JSON.stringify({
			success: true,
			data: results[0]
		  }), {
			headers: { 'Content-Type': 'application/json' }
		  });
		} catch (error) {
		  return new Response(JSON.stringify({
			success: false,
			error: error.message
		  }), {
			status: 500,
			headers: { 'Content-Type': 'application/json' }
		  });
		}
	  } else if (request.method === 'PUT') {
		try {
		  const data = await request.json();

		  // Update user in database
		  await env.DB.prepare(
			"UPDATE users SET name = ?, email = ? WHERE id = ?"
		  ).bind(data.name, data.email, userId).run();

		  return new Response(JSON.stringify({
			success: true,
			message: 'User updated successfully'
		  }), {
			headers: { 'Content-Type': 'application/json' }
		  });
		} catch (error) {
		  return new Response(JSON.stringify({
			success: false,
			error: error.message
		  }), {
			status: 500,
			headers: { 'Content-Type': 'application/json' }
		  });
		}
	  } else if (request.method === 'DELETE') {
		try {
		  // Delete user from database
		  await env.DB.prepare(
			"DELETE FROM users WHERE id = ?"
		  ).bind(userId).run();

		  return new Response(JSON.stringify({
			success: true,
			message: 'User deleted successfully'
		  }), {
			headers: { 'Content-Type': 'application/json' }
		  });
		} catch (error) {
		  return new Response(JSON.stringify({
			success: false,
			error: error.message
		  }), {
			status: 500,
			headers: { 'Content-Type': 'application/json' }
		  });
		}
	  }
	}

	// Not found for unhandled API routes
	return new Response(JSON.stringify({
	  success: false,
	  error: 'Not found'
	}), {
	  status: 404,
	  headers: { 'Content-Type': 'application/json' }
	});
  }
